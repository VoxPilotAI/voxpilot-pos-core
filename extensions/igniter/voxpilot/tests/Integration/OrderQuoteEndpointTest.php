<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\MenuAvailability;
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
