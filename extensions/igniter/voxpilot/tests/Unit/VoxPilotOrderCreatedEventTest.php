<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Events\VoxPilotOrderCreated;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class VoxPilotOrderCreatedEventTest extends TestCase
{
    public function test_broadcasts_on_private_tenant_location_channel(): void
    {
        $order = new Order();
        $order->order_id = 1;

        $metadata = new VoxPilotOrderMetadata();
        $metadata->external_order_id = 'vp_test_001';
        $metadata->source = 'voice';

        $event = new VoxPilotOrderCreated($order, $metadata, tenantId: 5, locationId: 10);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-tenant.5.location.10.orders', $channels[0]->name);
    }

    public function test_broadcast_as_returns_custom_name(): void
    {
        $order = new Order();
        $metadata = new VoxPilotOrderMetadata();

        $event = new VoxPilotOrderCreated($order, $metadata, tenantId: 1, locationId: 1);

        $this->assertEquals('voxpilot.order.created', $event->broadcastAs());
    }
}
