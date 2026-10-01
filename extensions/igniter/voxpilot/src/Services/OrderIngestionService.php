<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Order;
use Igniter\Cart\Models\OrderMenu;
use Igniter\Cart\Models\OrderMenuOptionValue;
use Igniter\Cart\Models\OrderTotal;
use Igniter\Local\Models\Location;
use Igniter\Local\Models\LocationArea;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Events\VoxPilotOrderCreated;
use Illuminate\Support\Facades\DB;

class OrderIngestionService
{
    public function ingest(array $payload, Tenant $tenant, TenantApiToken $token): array
    {
        $idempotencyKey = $payload['external_order_id'];

        $existing = $this->findExistingOrder($tenant->id, $idempotencyKey);
        if ($existing) {
            return ['order' => $existing['order'], 'metadata' => $existing['metadata'], 'created' => false];
        }

        $locationId = $this->resolveLocationId($payload, $tenant, $token);

        $matcher = new MenuMatcher($locationId);
        $matchResult = $matcher->matchItems($payload['items'] ?? []);

        $deliveryFee = $this->computeDeliveryFee($payload, $locationId, $matchResult['matched']);

        $result = DB::transaction(function () use ($payload, $tenant, $locationId, $idempotencyKey, $matchResult, $deliveryFee) {
            $order = $this->createOrder($payload, $tenant, $locationId, $matchResult['unmapped']);
            $this->createOrderMenus($order, $matchResult['matched'], $matchResult['unmapped']);
            $this->createOrderTotals($order, $deliveryFee);
            $metadata = $this->createMetadata($order, $tenant, $locationId, $payload, $idempotencyKey);

            return [
                'order' => $order->fresh(['menus', 'location']),
                'metadata' => $metadata,
                'created' => true,
                'unmapped' => $matchResult['unmapped'],
                'price_mismatch' => $this->hasPriceMismatch($matchResult['matched']),
            ];
        });

        try {
            VoxPilotOrderCreated::dispatch(
                $result['order'],
                $result['metadata'],
                $tenant->id,
                $locationId,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[voxpilot] broadcast failed: ' . $e->getMessage());
        }

        return $result;
    }

    protected function findExistingOrder(int $tenantId, string $externalOrderId): ?array
    {
        $metadata = VoxPilotOrderMetadata::where('tenant_id', $tenantId)
            ->where('external_order_id', $externalOrderId)
            ->first();

        if (!$metadata) {
            return null;
        }

        return ['order' => $metadata->order, 'metadata' => $metadata];
    }

    public function resolveLocationId(array $payload, Tenant $tenant, TenantApiToken $token): int
    {
        if (!empty($payload['location_id'])) {
            $location = Location::where('location_id', $payload['location_id'])
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$location) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    response()->json([
                        'error' => [
                            'code' => 'LOCATION_FORBIDDEN',
                            'message' => 'Location does not belong to this tenant.',
                        ],
                    ], 403)
                );
            }

            return $location->location_id;
        }

        if ($token->default_location_id) {
            return $token->default_location_id;
        }

        $tenantLocations = Location::where('tenant_id', $tenant->id)->get();
        if ($tenantLocations->count() === 1) {
            return $tenantLocations->first()->location_id;
        }

        throw new \Illuminate\Validation\ValidationException(
            validator([], []),
            response()->json([
                'error' => [
                    'code' => 'LOCATION_REQUIRED',
                    'message' => 'Could not resolve location. Provide location_id in payload or set a default location on the API token.',
                ],
            ], 422)
        );
    }

    protected function createOrder(array $payload, Tenant $tenant, int $locationId, array $unmapped): Order
    {
        $customer = $payload['customer'] ?? [];
        $fulfillment = $payload['fulfillment'] ?? [];
        $nameParts = $this->splitName($customer['name'] ?? 'Guest');

        $orderType = match ($fulfillment['type'] ?? 'pickup') {
            'delivery' => Order::DELIVERY,
            default => Order::COLLECTION,
        };

        $isAsap = strtoupper($fulfillment['requested_time'] ?? 'ASAP') === 'ASAP';

        $comment = $payload['notes'] ?? null;
        if (!empty($unmapped)) {
            $unmappedNames = array_map(fn($i) => ($i['quantity'] ?? 1) . '× ' . ($i['name'] ?? '?'), $unmapped);
            $warning = '⚠ Sin mapear: ' . implode(', ', $unmappedNames);
            $comment = $comment ? $comment . "\n" . $warning : $warning;
        }

        $order = new Order();
        $order->tenant_id = $tenant->id;
        $order->location_id = $locationId;
        $order->first_name = $nameParts['first'];
        $order->last_name = $nameParts['last'];
        $order->email = $customer['email'] ?? '';
        $order->telephone = $customer['phone'] ?? '';
        $order->order_type = $orderType;
        $order->order_date = now()->toDateString();
        $order->order_time = now()->format('H:i');
        $order->order_time_is_asap = $isAsap;
        $order->comment = $comment;
        $order->payment = '';
        $order->ip_address = request()->ip() ?? '0.0.0.0';
        $order->user_agent = 'VoxPilot API';
        $order->processed = false;
        $order->status_id = $this->getDefaultStatusId();
        if ($orderType === Order::DELIVERY && !empty($fulfillment['address'])) {
            $addressLine = '📍 ' . $fulfillment['address'];
            $order->comment = $order->comment ? $addressLine . "\n" . $order->comment : $addressLine;
        }
        $order->save();

        return $order;
    }

    protected function createOrderMenus(Order $order, array $matched, array $unmapped): void
    {
        $totalItems = 0;
        $subtotal = 0;

        foreach ($matched as $match) {
            $quantity = $match['quantity'];
            $price = $match['unit_price'];
            $itemSubtotal = $match['line_total'];

            $orderMenu = new OrderMenu();
            $orderMenu->order_id = $order->order_id;
            $orderMenu->menu_id = $match['menu_id'];
            $orderMenu->name = $match['name'];
            $orderMenu->quantity = $quantity;
            $orderMenu->price = $price;
            $orderMenu->subtotal = $itemSubtotal;
            $orderMenu->option_values = '';
            $orderMenu->comment = $match['item']['notes'] ?? '';
            $orderMenu->save();

            if (!empty($match['option']['matched']) && !empty($match['option']['option_value_id'])) {
                $this->createOptionValue($orderMenu, $match['option']);
            }

            $totalItems += $quantity;
            $subtotal += $itemSubtotal;
        }

        foreach ($unmapped as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (float) ($item['unit_price'] ?? 0);
            $itemSubtotal = $quantity * $price;

            $orderMenu = new OrderMenu();
            $orderMenu->order_id = $order->order_id;
            $orderMenu->menu_id = 0;
            $orderMenu->name = $item['name'] . ' [sin mapear]';
            $orderMenu->quantity = $quantity;
            $orderMenu->price = $price;
            $orderMenu->subtotal = $itemSubtotal;
            $orderMenu->option_values = '';
            $orderMenu->comment = $item['notes'] ?? '';
            $orderMenu->save();

            $totalItems += $quantity;
            $subtotal += $itemSubtotal;
        }

        $order->total_items = $totalItems;
        $order->order_total = $subtotal;
        $order->save();
    }

    protected function createOptionValue(OrderMenu $orderMenu, array $option): void
    {
        if (!class_exists(OrderMenuOptionValue::class)) {
            return;
        }

        try {
            $oov = new OrderMenuOptionValue();
            // The admin order view and Incoming Orders read options by order; without order_id the
            // size ("Mediana") was saved but never shown to the kitchen.
            $oov->order_id = $orderMenu->order_id;
            $oov->order_menu_id = $orderMenu->order_menu_id;
            $oov->menu_option_id = $option['menu_option_id'] ?? 0;
            $oov->menu_option_value_id = $option['option_value_id'];
            $oov->order_option_name = $option['name'] ?? '';
            $oov->order_option_price = $option['price'] ?? 0;
            $oov->quantity = 1;
            $oov->save();
        } catch (\Throwable) {
            // Option value storage is best-effort
        }
    }

    protected function createOrderTotals(Order $order, float $deliveryFee): void
    {
        $subtotal = $order->order_total ?? 0;

        $sub = new OrderTotal();
        $sub->order_id = $order->order_id;
        $sub->code = 'subtotal';
        $sub->title = 'Sub Total';
        $sub->value = $subtotal;
        $sub->priority = 0;
        $sub->is_summable = false;
        $sub->save();

        if ($deliveryFee > 0) {
            $fee = new OrderTotal();
            $fee->order_id = $order->order_id;
            $fee->code = 'delivery';
            $fee->title = 'Delivery';
            $fee->value = $deliveryFee;
            $fee->priority = 100;
            $fee->is_summable = true;
            $fee->save();

            $order->order_total = $subtotal + $deliveryFee;
            $order->save();
        }

        $totalRecord = new OrderTotal();
        $totalRecord->order_id = $order->order_id;
        $totalRecord->code = 'total';
        $totalRecord->title = 'Order Total';
        $totalRecord->value = $order->order_total ?? 0;
        $totalRecord->priority = 999;
        $totalRecord->is_summable = false;
        $totalRecord->save();
    }

    protected function computeDeliveryFee(array $payload, int $locationId, array $matched): float
    {
        $fulfillment = $payload['fulfillment'] ?? [];
        if (($fulfillment['type'] ?? 'pickup') !== 'delivery') {
            return 0;
        }

        $areas = LocationArea::where('location_id', $locationId)->orderBy('priority')->get();
        if ($areas->isEmpty()) {
            return 0;
        }

        $subtotal = array_sum(array_map(fn($m) => $m['line_total'] ?? 0, $matched));

        foreach ($areas as $area) {
            $conditions = is_array($area->conditions) ? $area->conditions : [];
            foreach ($conditions as $condition) {
                $total = (float) ($condition['total'] ?? $condition['amount'] ?? 0);
                $charge = (float) ($condition['charge'] ?? $condition['delivery_charge'] ?? 0);
                $type = $condition['type'] ?? 'above';

                if ($type === 'above' && $subtotal > $total) {
                    return $charge;
                }
                if ($type === 'below' && $subtotal <= $total) {
                    return $charge;
                }
            }
        }

        return 0;
    }

    protected function hasPriceMismatch(array $matched): bool
    {
        foreach ($matched as $m) {
            if (!empty($m['price_mismatch'])) {
                return true;
            }
        }
        return false;
    }

    protected function createMetadata(
        Order $order,
        Tenant $tenant,
        int $locationId,
        array $payload,
        string $idempotencyKey,
    ): VoxPilotOrderMetadata {
        return VoxPilotOrderMetadata::create([
            'order_id' => $order->order_id,
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'external_order_id' => $payload['external_order_id'],
            'call_sid' => $payload['call_sid'] ?? null,
            'source' => $payload['source'] ?? 'voice',
            'transcript' => $payload['transcript'] ?? null,
            'raw_payload' => $payload['raw'] ?? null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    protected function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [
            'first' => $parts[0],
            'last' => $parts[1] ?? '',
        ];
    }

    protected function getDefaultStatusId(): int
    {
        $status = DB::table('statuses')
            ->where('status_for', 'order')
            ->orderBy('status_id')
            ->first();

        return $status?->status_id ?? 1;
    }
}
