<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Order;
use Igniter\Cart\Models\OrderMenu;
use Igniter\Cart\Models\OrderTotal;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
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

        return DB::transaction(function () use ($payload, $tenant, $locationId, $idempotencyKey) {
            $order = $this->createOrder($payload, $tenant, $locationId);
            $this->createOrderMenus($order, $payload['items'] ?? []);
            $this->createOrderTotals($order);
            $metadata = $this->createMetadata($order, $tenant, $locationId, $payload, $idempotencyKey);

            return ['order' => $order->fresh(), 'metadata' => $metadata, 'created' => true];
        });
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

    protected function createOrder(array $payload, Tenant $tenant, int $locationId): Order
    {
        $customer = $payload['customer'] ?? [];
        $fulfillment = $payload['fulfillment'] ?? [];
        $nameParts = $this->splitName($customer['name'] ?? 'Guest');

        $orderType = match ($fulfillment['type'] ?? 'pickup') {
            'delivery' => Order::DELIVERY,
            default => Order::COLLECTION,
        };

        $isAsap = strtoupper($fulfillment['requested_time'] ?? 'ASAP') === 'ASAP';

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
        $order->comment = $payload['notes'] ?? null;
        $order->payment = '';
        $order->ip_address = request()->ip() ?? '0.0.0.0';
        $order->user_agent = 'VoxPilot API';
        $order->processed = false;
        $order->status_id = $this->getDefaultStatusId();
        $order->save();

        return $order;
    }

    protected function createOrderMenus(Order $order, array $items): void
    {
        $totalItems = 0;
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = (int)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $itemSubtotal = $quantity * $price;

            $orderMenu = new OrderMenu();
            $orderMenu->order_id = $order->order_id;
            $orderMenu->menu_id = 0;
            $orderMenu->name = $item['name'];
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

    protected function createOrderTotals(Order $order): void
    {
        $total = new OrderTotal();
        $total->order_id = $order->order_id;
        $total->code = 'subtotal';
        $total->title = 'Sub Total';
        $total->value = $order->order_total ?? 0;
        $total->priority = 0;
        $total->is_summable = false;
        $total->save();

        $totalRecord = new OrderTotal();
        $totalRecord->order_id = $order->order_id;
        $totalRecord->code = 'total';
        $totalRecord->title = 'Order Total';
        $totalRecord->value = $order->order_total ?? 0;
        $totalRecord->priority = 999;
        $totalRecord->is_summable = false;
        $totalRecord->save();
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
        // TastyIgniter status ID for "Pending" — typically ID 1
        $status = DB::table('statuses')
            ->where('status_for', 'order')
            ->orderBy('status_id')
            ->first();

        return $status?->status_id ?? 1;
    }
}
