<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Local\Models\WorkingHour;
use Igniter\VoxPilot\Jobs\NotifyStoreChange;
use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Services\StoreChangeNotifier;
use Igniter\VoxPilot\Services\StoreStatus;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Menu and store changes reach VoxPilot as signed events, only for restaurants connected to it. */
class NotifyStoreChangeTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_posts_a_signed_store_event(): void
    {
        config(['voxpilot.hmac_shared_secret' => str_repeat('s', 64), 'voxpilot.api_url' => 'http://backend:3000']);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $tenant = $this->makeTenant();

        (new NotifyStoreChange($tenant->id, 7, 'store.changed'))->handle();

        Http::assertSent(function (Request $request) use ($tenant) {
            $ts = $request->header('X-VoxPilot-Timestamp')[0];

            return $request->url() === 'http://backend:3000/pos/webhooks/store-changed'
                && $request->header('X-VoxPilot-Signature')[0] === 'v1='.hash_hmac('sha256', $ts.'.'.$request->body(), str_repeat('s', 64))
                && $request['event'] === 'store.changed'
                && $request['tenant_id'] === $tenant->external_tenant_id
                && $request['location_id'] === 7;
        });
    }

    public function test_only_connected_restaurants_are_notified(): void
    {
        Queue::fake();
        $connected = $this->makeLocation($tenant = $this->makeTenant());
        Installation::create(['tenant_id' => $tenant->id, 'status' => Installation::STATUS_CONNECTED]);
        $notConnected = $this->makeLocation($this->makeTenant('Not connected'));

        (new StoreStatus())->setPaused($connected, true);
        (new StoreStatus())->setPaused($notConnected, true);
        StoreChangeNotifier::notify($connected, 'menu.changed');

        Queue::assertPushed(NotifyStoreChange::class, 2);
    }

    public function test_one_event_per_location_for_a_burst_of_changes_like_saving_the_opening_hours(): void
    {
        Queue::fake();
        $location = $this->makeLocation($tenant = $this->makeTenant());
        Installation::create(['tenant_id' => $tenant->id, 'status' => Installation::STATUS_CONNECTED]);

        foreach ([0, 1] as $weekday) {
            $hour = new WorkingHour();
            $hour->forceFill(['location_id' => $location->getKey(), 'type' => 'opening', 'weekday' => $weekday, 'opening_time' => '09:00', 'closing_time' => '10:00', 'status' => 1]);
            $hour->save();
        }
        StoreChangeNotifier::notify($location, 'store.changed');

        Queue::assertPushed(NotifyStoreChange::class, fn (NotifyStoreChange $job) => $job->delay !== null);
        Queue::assertPushed(NotifyStoreChange::class, 1);
    }
}
