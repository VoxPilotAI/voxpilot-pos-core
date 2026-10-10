<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Carbon\Carbon;
use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Jobs\NotifyStatusChange;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;

/** Status (and kitchen estimate) of a VoxPilot order, sent to VoxPilot (NotifyStatusChange). */
class VoxPilotStatusNotifier
{
    /** Queues the event for a VoxPilot order; other orders are the restaurant's own. */
    public static function orderChanged(Order $order, ?string $statusName = null, ?string $comment = null): void
    {
        $metadata = VoxPilotOrderMetadata::where('order_id', $order->order_id)->first();
        if (!$metadata) {
            return;
        }

        NotifyStatusChange::dispatch(
            (int) $order->order_id,
            (int) $metadata->tenant_id,
            (string) $metadata->external_order_id,
            $statusName ?? (string) ($order->status?->status_name ?? 'unknown'),
            $comment,
        );
    }

    /** The kitchen's estimate (the order time when it is not ASAP), or null. */
    public static function readyAt(Order $order): ?Carbon
    {
        if ($order->order_time_is_asap || !$order->order_date || !$order->order_time) {
            return null;
        }

        try {
            return Carbon::parse(Carbon::parse($order->order_date)->toDateString().' '.$order->order_time);
        } catch (\Throwable) {
            return null;
        }
    }
}
