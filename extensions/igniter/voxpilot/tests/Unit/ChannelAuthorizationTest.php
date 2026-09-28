<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChannelAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Auth Test Tenant',
            'slug' => 'auth-test-tenant-' . uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_unauthorized_user_cannot_subscribe(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No admin user available');
        }

        $this->actingAs($user, 'igniter-admin');

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.' . $this->tenant->id . '.location.1.orders',
        ]);

        $response->assertStatus(403);
    }

    public function test_authorized_member_can_subscribe(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No admin user available');
        }

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->user_id,
            'role' => 'admin',
        ]);

        $location = Location::first();
        if (!$location) {
            $this->markTestSkipped('No location available');
        }

        $location->tenant_id = $this->tenant->id;
        $location->save();

        $this->actingAs($user, 'igniter-admin');

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.' . $this->tenant->id . '.location.' . $location->location_id . '.orders',
        ]);

        $response->assertStatus(200);
    }

    public function test_member_cannot_subscribe_to_foreign_location(): void
    {
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No admin user available');
        }

        // Super users may subscribe to every location of their tenant; test a regular member.
        $user->super_user = false;
        $user->save();

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->user_id,
            'role' => 'member',
        ]);

        $this->actingAs($user, 'igniter-admin');

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.' . $this->tenant->id . '.location.99999.orders',
        ]);

        $response->assertStatus(403);
    }
}
