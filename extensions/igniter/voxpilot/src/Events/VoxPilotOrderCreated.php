<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Events;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Support\OrderLine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoxPilotOrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly VoxPilotOrderMetadata $metadata,
        public readonly int $tenantId,
        public readonly int $locationId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.location.{$this->locationId}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voxpilot.order.created';
    }

    public function broadcastWith(): array
    {
        $order = $this->order;
        $menus = $order->menus ?? collect();

        $fulfillmentType = $order->order_type === Order::DELIVERY ? 'delivery' : 'pickup';

        return [
            'order_id' => $order->order_id,
            'external_order_id' => $this->metadata->external_order_id,
            'tenant_id' => $this->tenantId,
            'location_id' => $this->locationId,
            'location_name' => $order->location?->location_name ?? '',
            'source' => $this->metadata->source,
            'customer' => [
                'name' => trim($order->first_name . ' ' . $order->last_name),
                'phone' => $order->telephone,
            ],
            'fulfillment_type' => $fulfillmentType,
            'items' => $menus->map(fn($menu) => [
                'name' => OrderLine::name($menu),
                'quantity' => $menu->quantity,
                'subtotal' => (float) $menu->subtotal,
                'subtotal_label' => currency_format($menu->subtotal),
                'notes' => $menu->comment ?: null,
            ])->values()->toArray(),
            'order_total' => (float) $order->order_total,
            'order_total_label' => currency_format($order->order_total),
            'notes' => $order->comment,
            'status' => 'Pending',
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
