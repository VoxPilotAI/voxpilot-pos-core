<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Events\VoxPilotOrderCreated;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderBroadcastTest extends TestCase
{
    public function test_order_creation_dispatches_event(): void
    {
        Event::fake([VoxPilotOrderCreated::class]);

        $tenant = Tenant::create([
            'name' => 'Broadcast Tenant',
            'slug' => 'broadcast-tenant',
            'status' => 'active',
        ]);

        $location = Location::first();
        if (!$location) {
            $this->markTestSkipped('No location available');
        }

        $location->tenant_id = $tenant->id;
        $location->save();

        $result = TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'Broadcast Test Token',
            defaultLocationId: $location->location_id,
        );

        $response = $this->postJson('/api/voxpilot/orders', [
            'external_order_id' => 'vp_broadcast_test_001',
            'source' => 'voice',
            'customer' => ['name' => 'Broadcast Test'],
            'fulfillment' => ['type' => 'pickup'],
            'items' => [
                ['name' => 'Test Item', 'quantity' => 1, 'unit_price' => 9.99],
            ],
        ], [
            'Authorization' => 'Bearer ' . $result['plain_text'],
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(VoxPilotOrderCreated::class, function ($event) use ($tenant, $location) {
            return $event->tenantId === $tenant->id
                && $event->locationId === $location->location_id;
        });
    }

    public function test_duplicate_order_does_not_dispatch_event(): void
    {
        Event::fake([VoxPilotOrderCreated::class]);

        $tenant = Tenant::create([
            'name' => 'Dedup Tenant',
            'slug' => 'dedup-tenant',
            'status' => 'active',
        ]);

        $location = Location::first();
        if (!$location) {
            $this->markTestSkipped('No location available');
        }

        $location->tenant_id = $tenant->id;
        $location->save();

        $result = TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'Dedup Token',
            defaultLocationId: $location->location_id,
        );

        $headers = ['Authorization' => 'Bearer ' . $result['plain_text']];
        $payload = [
            'external_order_id' => 'vp_dedup_broadcast_001',
            'customer' => ['name' => 'Dedup Test'],
            'fulfillment' => ['type' => 'pickup'],
            'items' => [['name' => 'Item', 'quantity' => 1]],
        ];

        $this->postJson('/api/voxpilot/orders', $payload, $headers)->assertStatus(201);

        Event::assertDispatchedTimes(VoxPilotOrderCreated::class, 1);

        $this->postJson('/api/voxpilot/orders', $payload, $headers)->assertStatus(200);

        Event::assertDispatchedTimes(VoxPilotOrderCreated::class, 1);
    }
}
