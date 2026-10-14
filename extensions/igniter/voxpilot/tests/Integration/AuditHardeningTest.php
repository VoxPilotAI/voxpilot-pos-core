<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantForAdmin;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Services\TenantContext;
use Igniter\VoxPilot\Services\VoxPilotWebhook;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Fixes from the pos-gateway audit: token abilities, fail-closed tenant scope, races and signing. */
class AuditHardeningTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_a_menu_only_token_cannot_touch_orders(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $menuOnly = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Menu', defaultLocationId: $location->getKey(), abilities: ['menu:read'])['plain_text'];

        $this->getJson('/api/voxpilot/menu', ['Authorization' => 'Bearer '.$menuOnly])->assertOk();
        $this->postJson('/api/voxpilot/orders/ord_x/cancel', [], ['Authorization' => 'Bearer '.$menuOnly])
            ->assertStatus(403)->assertJsonPath('error.code', 'ABILITY_MISSING');
        $this->postJson('/api/voxpilot/orders/quote', ['fulfillment' => ['type' => 'pickup'], 'items' => [['name' => 'x', 'quantity' => 1]]], ['Authorization' => 'Bearer '.$menuOnly])
            ->assertStatus(403);
    }

    public function test_an_admin_without_a_restaurant_sees_no_orders_or_locations(): void
    {
        $this->makeLocation($this->makeTenant());
        $context = app(TenantContext::class);
        $this->assertGreaterThan(0, Location::query()->count());

        $context->denyAll();

        $this->assertSame(0, Location::query()->count());
        $this->assertSame(0, Order::query()->count());
        $context->clear();
    }

    public function test_a_kitchen_accepted_order_cannot_be_cancelled_by_phone_even_late(): void
    {
        Queue::fake();
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Orders', defaultLocationId: $location->getKey(), abilities: ['orders:create', 'menu:read'])['plain_text'];
        $this->postJson('/api/voxpilot/orders', [
            'external_order_id' => 'ord_paid_1', 'customer' => ['name' => 'Ana'], 'fulfillment' => ['type' => 'pickup'],
            'items' => [['name' => 'Pizza Margherita', 'quantity' => 1]],
        ], ['Authorization' => 'Bearer '.$token])->assertStatus(201);
        $order = Order::withoutGlobalScopes()->latest('order_id')->first();
        $order->processed = true;
        $order->saveQuietly();

        $this->postJson('/api/voxpilot/orders/ord_paid_1/cancel', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(409)->assertJsonPath('error.code', 'ORDER_LOCKED');
        $this->assertFalse($order->fresh()->isCanceled());
    }

    public function test_an_order_with_an_item_not_available_now_tells_the_kitchen(): void
    {
        Queue::fake();
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $hawaiana = $this->makeMenu($location, 'Pizza Hawaiana', 12.99);
        (new MenuAvailability())->setUnavailableToday($location, $hawaiana->getKey(), true);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Orders', defaultLocationId: $location->getKey())['plain_text'];

        $this->postJson('/api/voxpilot/orders', [
            'external_order_id' => 'ord_late_1', 'customer' => ['name' => 'Ana'], 'fulfillment' => ['type' => 'pickup'],
            'items' => [['name' => 'Pizza Hawaiana', 'quantity' => 2]],
        ], ['Authorization' => 'Bearer '.$token])->assertStatus(201);

        $this->assertStringContainsString('2× Pizza Hawaiana', (string) Order::withoutGlobalScopes()->latest('order_id')->value('comment'));
    }

    public function test_never_sends_an_unsigned_event(): void
    {
        config(['voxpilot.hmac_shared_secret' => '', 'voxpilot.api_url' => 'http://backend:3000']);
        Http::fake();

        $this->assertNull((new VoxPilotWebhook())->send($this->makeTenant(), '/pos/webhooks/store-changed', 'menu.changed', ['event' => 'menu.changed']));
        Http::assertNothingSent();
    }
}
