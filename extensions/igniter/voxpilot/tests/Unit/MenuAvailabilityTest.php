<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Cart\Models\Stock;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** "Not available today": a dish cooked to order stops being offered until tomorrow, stock untouched. */
class MenuAvailabilityTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_marks_a_dish_not_available_today_without_touching_its_stock(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $menu = $this->makeMenu($location, 'Pizza Hawaiana');
        $availability = new MenuAvailability();

        $availability->setUnavailableToday($location, $menu->getKey(), true);

        $item = $availability->items($location)->firstWhere('id', $menu->getKey());
        $this->assertTrue($item['unavailable_today']);
        $this->assertTrue($item['sold_out']);
        $this->assertFalse($item['out_of_stock']);
        $this->assertFalse(Stock::where('stockable_id', $menu->getKey())->where('is_tracked', true)->exists());

        $availability->setUnavailableToday($location, $menu->getKey(), false);

        $this->assertFalse($availability->items($location)->firstWhere('id', $menu->getKey())['sold_out']);
    }

    public function test_the_dish_is_back_on_its_own_the_next_day(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $menu = $this->makeMenu($location, 'Pizza Hawaiana');
        $other = $this->makeMenu($location, 'Calzone');
        $availability = new MenuAvailability();

        Carbon::setTestNow(Carbon::parse('2026-10-09 21:30'));
        $availability->setUnavailableToday($location, $menu->getKey(), true);
        $this->assertSame([(int) $menu->getKey()], $availability->unavailableToday($location));

        Carbon::setTestNow(Carbon::parse('2026-10-10 08:00'));
        $this->assertSame([], $availability->unavailableToday($location));

        // The expired mark is dropped the next time the list is written.
        $availability->setUnavailableToday($location, $other->getKey(), true);
        $marks = (array) ((new \Igniter\VoxPilot\Services\StoreStatus())->own($location->fresh())[MenuAvailability::KEY] ?? []);
        $this->assertSame([(string) $other->getKey()], array_map('strval', array_keys($marks)));
    }

    public function test_tracked_stock_that_ran_out_shows_as_out_of_stock(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $menu = $this->makeMenu($location, 'Coca-Cola 600 ml');
        $stock = $menu->getStockByLocation($location);
        $stock->is_tracked = true;
        $stock->save();

        $item = (new MenuAvailability())->items($location)->firstWhere('id', $menu->getKey());

        $this->assertTrue($item['out_of_stock']);
        $this->assertFalse($item['unavailable_today']);
        $this->assertTrue($item['sold_out']);
    }

    public function test_items_of_another_location_cannot_be_changed(): void
    {
        $mine = $this->makeLocation($this->makeTenant());
        $other = $this->makeLocation($this->makeTenant('Other'));
        $menu = $this->makeMenu($other, 'Not mine');

        $this->expectException(ModelNotFoundException::class);

        (new MenuAvailability())->setUnavailableToday($mine, $menu->getKey(), true);
    }
}
