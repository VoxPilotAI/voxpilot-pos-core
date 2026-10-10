<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Jobs\NotifyStoreChange;
use Igniter\VoxPilot\Models\Installation;
use Illuminate\Support\Facades\Cache;

/** Queues a menu/store change event for VoxPilot, only for restaurants connected to VoxPilot. */
class StoreChangeNotifier
{
    /**
     * One event per location and kind within this window: saving the opening hours writes a row per
     * weekday. The job runs when the window closes, so it covers every change made inside it.
     */
    public const DEBOUNCE_SECONDS = 3;

    public static function notify(Location $location, string $event): void
    {
        $tenantId = (int) $location->tenant_id;
        if (!$tenantId) {
            return;
        }

        $connected = Installation::where('tenant_id', $tenantId)->where('status', Installation::STATUS_CONNECTED)->exists();
        if ($connected && Cache::add('voxpilot:store-change:'.$location->getKey().':'.$event, true, self::DEBOUNCE_SECONDS)) {
            NotifyStoreChange::dispatch($tenantId, (int) $location->getKey(), $event)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        }
    }

    /** Every location an item is sold at (menu edits in the TastyIgniter admin). */
    public static function notifyMenuLocations(iterable $locations): void
    {
        foreach ($locations as $location) {
            self::notify($location, 'menu.changed');
        }
    }
}
