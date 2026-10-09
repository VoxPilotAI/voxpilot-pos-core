<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Sold out with one click, through TastyIgniter's stock override. */
class MenuAvailabilityTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_untracked_item_is_sold_out_and_back_with_its_stock_settings_restored(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $menu = $this->makeMenu($location, 'Margherita');
        $availability = new MenuAvailability();

        $availability->setSoldOut($location, $menu->getKey(), true);

        $stock = $menu->fresh()->getStockByLocation($location);
        $this->assertTrue($stock->is_tracked);
        $this->assertTrue($stock->outOfStock());
        $this->assertTrue($menu->fresh()->outOfStock($location));
        $this->assertTrue($availability->items($location)->firstWhere('id', $menu->getKey())['sold_out']);

        $availability->setSoldOut($location, $menu->getKey(), false);

        $stock = $stock->fresh();
        $this->assertFalse($stock->is_tracked);
        $this->assertFalse($stock->outOfStock());
        $this->assertFalse($availability->items($location)->firstWhere('id', $menu->getKey())['sold_out']);
    }

    public function test_tracked_item_keeps_its_tracking(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $menu = $this->makeMenu($location, 'Diavola');
        $stock = $menu->getStockByLocation($location);
        $stock->is_tracked = true;
        $stock->save();
        $stock->updateStock(12, \Igniter\Cart\Models\Stock::STATE_RECOUNT);
        $availability = new MenuAvailability();

        $availability->setSoldOut($location, $menu->getKey(), true);
        $availability->setSoldOut($location, $menu->getKey(), false);

        $stock = $stock->fresh();
        $this->assertTrue($stock->is_tracked);
        $this->assertSame(12, $stock->quantity);
        $this->assertFalse($stock->outOfStock());
    }

    public function test_items_of_another_location_cannot_be_changed(): void
    {
        $mine = $this->makeLocation($this->makeTenant());
        $other = $this->makeLocation($this->makeTenant('Other'));
        $menu = $this->makeMenu($other, 'Not mine');

        $this->expectException(ModelNotFoundException::class);

        (new MenuAvailability())->setSoldOut($mine, $menu->getKey(), true);
    }
}
