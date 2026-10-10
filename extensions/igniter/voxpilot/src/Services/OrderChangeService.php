<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Order;
use Igniter\Cart\Models\OrderMenu;
use Igniter\Cart\Models\OrderMenuOptionValue;
use Igniter\Cart\Models\OrderTotal;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Support\Facades\DB;

/**
 * A caller changes or cancels their order by phone (pos-gateway SPEC-005). Only while the kitchen
 * has not accepted it yet (still in the first order status): after that the staff decide.
 */
class OrderChangeService extends OrderIngestionService
{
    public const LOCKED = 'ORDER_LOCKED';

    public const NOT_FOUND = 'NOT_FOUND';

    public const ITEMS_UNAVAILABLE = 'ITEMS_UNAVAILABLE';

    /** The tenant's order VoxPilot created with this id. */
    public function find(Tenant $tenant, string $externalOrderId): ?Order
    {
        $metadata = VoxPilotOrderMetadata::where('tenant_id', $tenant->id)
            ->where('external_order_id', $externalOrderId)
            ->first();

        return $metadata ? Order::withoutGlobalScopes()->with('status')->find($metadata->order_id) : null;
    }

    /** Still in the status VoxPilot orders are created with (the kitchen has not accepted it) and not paid. */
    public function canChange(Order $order): bool
    {
        return (int) $order->status_id === $this->getDefaultStatusId() && !$order->isCanceled() && !$order->processed;
    }

    /** Returns false when the kitchen accepted it in the meantime (checked again under a row lock). */
    public function cancel(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::withoutGlobalScopes()->lockForUpdate()->find($order->order_id);
            if (!$locked || !$this->canChange($locked)) {
                return false;
            }
            $locked->markAsCanceled(['comment' => lang('igniter.voxpilot::orders.canceled_by_phone'), 'notify' => false]);

            return true;
        });
    }

    /**
     * Replaces the items (and optionally pickup/delivery) of the order with the caller's new list.
     *
     * @return array{error?: string, unmapped?: array, unavailable?: array, order?: Order}
     */
    public function replaceItems(Order $order, array $payload, Tenant $tenant, TenantApiToken $token): array
    {
        $type = ($payload['fulfillment']['type'] ?? null) ?: ($order->order_type === Order::DELIVERY ? 'delivery' : 'pickup');
        $request = ['location_id' => $order->location_id, 'fulfillment' => ['type' => $type], 'items' => $payload['items']];

        $quote = $this->quote($request, $tenant, $token);
        if ($quote['unmapped'] || $quote['unavailable']) {
            return ['error' => self::ITEMS_UNAVAILABLE, 'unmapped' => $quote['unmapped'], 'unavailable' => $quote['unavailable']];
        }

        $matched = (new MenuMatcher((int) $order->location_id))->matchItems($payload['items'])['matched'];
        $deliveryFee = $this->computeDeliveryFee($request, (int) $order->location_id, $matched);

        $changed = DB::transaction(function () use ($order, $matched, $deliveryFee, $type, $payload): bool {
            // The kitchen may have accepted it while the new items were priced.
            $order = Order::withoutGlobalScopes()->lockForUpdate()->find($order->order_id);
            if (!$order || !$this->canChange($order)) {
                return false;
            }
            if (class_exists(OrderMenuOptionValue::class)) {
                OrderMenuOptionValue::where('order_id', $order->order_id)->delete();
            }
            OrderMenu::where('order_id', $order->order_id)->delete();
            // Only the totals VoxPilot wrote; anything else (coupons, taxes) stays.
            OrderTotal::where('order_id', $order->order_id)->whereIn('code', ['subtotal', 'delivery', 'total'])->delete();

            $order->order_type = $type === 'delivery' ? Order::DELIVERY : Order::COLLECTION;
            $note = lang('igniter.voxpilot::orders.changed_by_phone');
            $lines = array_filter(preg_split('/\R/', (string) $order->comment), fn ($l) => !str_starts_with($l, '📍') && $l !== $note);
            if ($type === 'delivery' && !empty($payload['fulfillment']['address'])) {
                array_unshift($lines, '📍 '.$payload['fulfillment']['address']);
            }
            $lines[] = $note;
            $order->comment = implode("\n", $lines);
            $order->save();

            $this->createOrderMenus($order, $matched, []);
            $this->createOrderTotals($order, $deliveryFee);

            return true;
        });
        if (!$changed) {
            return ['error' => self::LOCKED];
        }

        return ['order' => $order->fresh(['menus', 'status'])];
    }
}
