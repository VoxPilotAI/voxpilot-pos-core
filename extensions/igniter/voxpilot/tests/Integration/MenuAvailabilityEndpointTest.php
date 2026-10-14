<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Services\StoreStatus;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** The menu VoxPilot reads lists only what the restaurant can sell now, plus the store status (SPEC-001). */
class MenuAvailabilityEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_menu_leaves_out_unavailable_items_and_reports_the_store(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $pizza = $this->makeMenu($location, 'Pizza Hawaiana', 12.99);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        $soda = $this->makeMenu($location, 'Coca-Cola 600 ml', 2.5);
        $stock = $soda->getStockByLocation($location);
        $stock->is_tracked = true;
        $stock->save();
        (new MenuAvailability())->setUnavailableToday($location, $pizza->getKey(), true);
        (new StoreStatus())->setBusy($location, 15);
        (new StoreStatus())->setOrderType($location, 'delivery', false);
        $token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Menu', defaultLocationId: $location->getKey())['plain_text'];

        $response = $this->getJson('/api/voxpilot/menu', ['Authorization' => 'Bearer '.$token]);

        $response->assertOk();
        $this->assertSame(['Pizza Margherita'], collect($response->json('data.items'))->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            [['name' => 'Pizza Hawaiana', 'reason' => 'not_available_today'], ['name' => 'Coca-Cola 600 ml', 'reason' => 'out_of_stock']],
            collect($response->json('data.unavailable'))->map(fn ($u) => ['name' => $u['name'], 'reason' => $u['reason']])->all(),
        );
        $response->assertJsonPath('data.store.busy_minutes', 15);
        $response->assertJsonPath('data.store.delivery_enabled', false);
        $response->assertJsonPath('data.store.collection_enabled', true);
        $response->assertJsonPath('data.store.paused', false);
    }
}
