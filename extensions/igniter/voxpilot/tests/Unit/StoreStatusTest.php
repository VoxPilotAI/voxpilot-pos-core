<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Services\StoreStatus;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Busy, paused and delivery/pickup switches of the board's store bar. */
class StoreStatusTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected function location(): Location
    {
        $location = $this->makeLocation($this->makeTenant());
        $store = new StoreStatus();
        $store->write($location, 'delivery', ['lead_time' => 25, 'is_enabled' => 1, 'min_order_amount' => 5]);
        $store->write($location, 'collection', ['lead_time' => 10, 'is_enabled' => 1]);

        return $location;
    }

    public function test_busy_adds_minutes_to_both_lead_times_and_restores_them(): void
    {
        $location = $this->location();
        $store = new StoreStatus();

        $store->setBusy($location, 15);
        $store->setBusy($location, 30);
        $busy = $store->snapshot($location);

        $this->assertTrue($busy['busy']);
        $this->assertSame(30, $busy['busy_minutes']);
        $this->assertSame(55, $busy['delivery_lead_time']);
        $this->assertSame(40, $busy['collection_lead_time']);

        $store->setBusy($location, 0);
        $normal = $store->snapshot($location);

        $this->assertFalse($normal['busy']);
        $this->assertSame(25, $normal['delivery_lead_time']);
        $this->assertSame(10, $normal['collection_lead_time']);
        // The owner's other delivery settings are kept.
        $this->assertSame(5, (int) $location->fresh()->getSettings('delivery.min_order_amount'));
    }

    public function test_rejects_unsupported_busy_minutes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new StoreStatus())->setBusy($this->location(), 17);
    }

    public function test_pause_closes_until_the_end_of_today(): void
    {
        $location = $this->location();
        $store = new StoreStatus();

        $store->setPaused($location, true);
        $paused = $store->snapshot($location);

        $this->assertTrue($paused['paused']);
        $this->assertFalse($paused['open']);
        $this->assertSame(now()->endOfDay()->toDateString(), substr((string) $paused['paused_until'], 0, 10));

        $store->setPaused($location, false);
        $this->assertFalse($store->snapshot($location)['paused']);
    }

    public function test_switches_delivery_and_pickup_in_tastyigniter_settings(): void
    {
        $location = $this->location();
        $store = new StoreStatus();

        $store->setOrderType($location, 'delivery', false);

        $this->assertFalse($location->fresh()->hasDelivery());
        $this->assertTrue($location->fresh()->hasCollection());
        $this->assertFalse($store->snapshot($location)['delivery_enabled']);
    }
}
