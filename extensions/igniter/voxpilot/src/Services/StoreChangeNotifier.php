<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Jobs\NotifyStoreChange;
use Igniter\VoxPilot\Models\Installation;

/** Queues a menu/store change event for VoxPilot, only for restaurants connected to VoxPilot. */
class StoreChangeNotifier
{
    public static function notify(Location $location, string $event): void
    {
        $tenantId = (int) $location->tenant_id;
        if (!$tenantId) {
            return;
        }

        $connected = Installation::where('tenant_id', $tenantId)->where('status', Installation::STATUS_CONNECTED)->exists();
        if ($connected) {
            NotifyStoreChange::dispatch($tenantId, (int) $location->getKey(), $event);
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
