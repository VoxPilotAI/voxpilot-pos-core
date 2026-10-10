<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Cart\Models\Order;
use Igniter\Local\Models\LocationArea;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Services\StoreStatus;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** The total the assistant says is the one the POS will charge, and nothing is created (SPEC-002). */
class OrderQuoteEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_quotes_with_pos_prices_and_reports_unknown_and_unavailable_items(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        $this->makeMenu($location, 'Coca-Cola', 2.5);
        $hawaiana = $this->makeMenu($location, 'Pizza Hawaiana', 12.99);
        (new MenuAvailability())->setUnavailableToday($location, $hawaiana->getKey(), true);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Quote', defaultLocationId: $location->getKey())['plain_text'];
        $orders = Order::withoutGlobalScopes()->count();

        $response = $this->postJson('/api/voxpilot/orders/quote', [
            'fulfillment' => ['type' => 'pickup'],
            'items' => [
                ['name' => 'pizza margherita', 'quantity' => 2, 'unit_price' => 9.99],
                ['name' => 'Coca-Cola', 'quantity' => 3],
                ['name' => 'Pizza Hawaiana', 'quantity' => 1],
                ['name' => 'Lasaña', 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer '.$token]);

        $response->assertOk();
        $response->assertJsonPath('data.subtotal', 29.48);
        $response->assertJsonPath('data.delivery_fee', 0);
        $response->assertJsonPath('data.total', 29.48);
        $response->assertJsonPath('data.lines.0.name', 'Pizza Margherita');
        $response->assertJsonPath('data.lines.0.unit_price', 10.99);
        $response->assertJsonPath('data.lines.0.price_mismatch', true);
        $response->assertJsonPath('data.unavailable.0.name', 'Pizza Hawaiana');
        $response->assertJsonPath('data.unmapped.0.name', 'Lasaña');
        $this->assertSame($orders, Order::withoutGlobalScopes()->count(), 'a quote creates no order');
    }

    public function test_delivery_fee_follows_the_tastyigniter_area_conditions(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        // As the admin's delivery area form saves them: `amount` is the charge, `total` the threshold.
        $area = new LocationArea([
            'name' => 'Centro',
            'type' => 'address',
            'boundaries' => ['components' => []],
            'conditions' => [
                ['type' => 'below', 'amount' => '3.50', 'total' => '30', 'priority' => 1],
                ['type' => 'above', 'amount' => '0', 'total' => '30', 'priority' => 2],
            ],
        ]);
        $area->location_id = $location->getKey();
        $area->save();
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Quote', defaultLocationId: $location->getKey())['plain_text'];
        $quote = fn (int $qty) => $this->postJson('/api/voxpilot/orders/quote', [
            'fulfillment' => ['type' => 'delivery'],
            'items' => [['name' => 'Pizza Margherita', 'quantity' => $qty]],
        ], ['Authorization' => 'Bearer '.$token]);

        $quote(1)->assertOk()->assertJsonPath('data.delivery_fee', 3.5)->assertJsonPath('data.total', 14.49);
        $quote(3)->assertOk()->assertJsonPath('data.delivery_fee', 0)->assertJsonPath('data.total', 32.97);
    }

    public function test_says_when_the_store_cannot_take_the_order_and_flags_one_that_arrives_anyway(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Quote', defaultLocationId: $location->getKey())['plain_text'];
        $auth = ['Authorization' => 'Bearer '.$token];
        $body = fn (string $type) => ['fulfillment' => ['type' => $type], 'items' => [['name' => 'Pizza Margherita', 'quantity' => 1]]];
        $store = new StoreStatus();

        $this->postJson('/api/voxpilot/orders/quote', $body('delivery'), $auth)->assertJsonPath('data.store_issue', null);

        $store->setOrderType($location, 'delivery', false);
        $this->postJson('/api/voxpilot/orders/quote', $body('delivery'), $auth)->assertJsonPath('data.store_issue', 'delivery_unavailable');
        $this->postJson('/api/voxpilot/orders/quote', $body('pickup'), $auth)->assertJsonPath('data.store_issue', null);

        $store->setPaused($location, true);
        $this->postJson('/api/voxpilot/orders/quote', $body('pickup'), $auth)->assertJsonPath('data.store_issue', 'store_closed');

        // A confirmed order is never lost: it is created, and the staff see why to call the customer.
        $created = $this->postJson('/api/voxpilot/orders', [
            'external_order_id' => 'vp_store_closed_'.uniqid(),
            'source' => 'voice',
            'customer' => ['name' => 'Ana Ruiz', 'phone' => '+50688554433'],
        ] + $body('pickup'), $auth);
        $created->assertStatus(201);
        $order = Order::withoutGlobalScopes()->find($created->json('data.order_id'));
        $this->assertStringContainsString(lang('igniter.voxpilot::orders.store_warning_store_closed'), (string) $order->comment);
    }

    public function test_needs_the_tenant_token_and_valid_items(): void
    {
        $this->postJson('/api/voxpilot/orders/quote', ['fulfillment' => ['type' => 'pickup'], 'items' => [['name' => 'x', 'quantity' => 1]]])
            ->assertStatus(401);

        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Quote', defaultLocationId: $location->getKey())['plain_text'];
        $this->postJson('/api/voxpilot/orders/quote', ['fulfillment' => ['type' => 'boat'], 'items' => []], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
