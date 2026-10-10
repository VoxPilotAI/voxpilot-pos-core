<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Carbon\Carbon;
use Igniter\Local\Facades\Location as LocationFacade;
use Igniter\Local\Models\Location;
use Igniter\Local\Models\LocationSettings;
use Illuminate\Support\Facades\DB;

/**
 * What the restaurant can take right now, per location: busy (extra minutes on top of the delivery
 * and pickup lead times), paused for the rest of the day, and delivery / pickup on or off.
 *
 * Delivery and pickup use TastyIgniter's own location settings (`delivery.*`, `collection.*`), so
 * the rest of TastyIgniter sees the same thing. Busy and paused live in the location's `voxpilot`
 * settings: busy keeps the owner's normal lead times there to restore them afterwards.
 */
class StoreStatus
{
    public const SETTINGS = 'voxpilot';

    /** Extra minutes the owner can pick when the kitchen is busy. */
    public const BUSY_STEPS = [0, 15, 30, 45];

    public const ORDER_TYPES = ['delivery', 'collection'];

    /** The location the admin works on: the selected one when it is the owner's, else their first. */
    public static function resolveLocation(): ?Location
    {
        $current = null;
        try {
            $current = LocationFacade::current();
        } catch (\Throwable) {
        }

        // The Location model is tenant-scoped, so this only finds the owner's own locations. Without a
        // restaurant (a platform super user) there is no "own" location to change.
        if (!app(TenantContext::class)->isActive()) {
            return null;
        }

        return ($current ? Location::query()->find($current->getKey()) : null)
            ?? Location::query()->orderBy('location_id')->first();
    }

    public function snapshot(Location $location): array
    {
        $location = $this->fresh($location);
        $own = $this->own($location);
        $closedUntil = !empty($own['closed_until']) ? Carbon::parse($own['closed_until']) : null;
        $paused = $closedUntil !== null && $closedUntil->isFuture();

        return [
            'location_id' => (int) $location->getKey(),
            'location_name' => (string) $location->location_name,
            'open' => !$paused && $this->scheduleOpen($location) !== false,
            'within_hours' => $this->scheduleOpen($location),
            'paused' => $paused,
            'paused_until' => $paused ? $closedUntil->toIso8601String() : null,
            'busy' => (int) ($own['busy_minutes'] ?? 0) > 0,
            'busy_minutes' => (int) ($own['busy_minutes'] ?? 0),
            'delivery_enabled' => (bool) $location->getSettings('delivery.is_enabled', 1),
            'collection_enabled' => (bool) $location->getSettings('collection.is_enabled', 1),
            'delivery_lead_time' => (int) $location->getSettings('delivery.lead_time', 15),
            'collection_lead_time' => (int) $location->getSettings('collection.lead_time', 15),
        ];
    }

    /**
     * The store block of the menu VoxPilot reads (GET /api/voxpilot/menu): whether to take orders
     * now, extra wait and which order types are on.
     *
     * @return array{open: bool, paused: bool, within_hours: ?bool, busy_minutes: int,
     *     delivery_enabled: bool, collection_enabled: bool, delivery_lead_time: int, collection_lead_time: int}
     */
    public function forVoxPilot(Location $location): array
    {
        $snapshot = $this->snapshot($location);

        return [
            'open' => $snapshot['open'],
            'paused' => $snapshot['paused'],
            'within_hours' => $snapshot['within_hours'],
            'busy_minutes' => $snapshot['busy_minutes'],
            'delivery_enabled' => $snapshot['delivery_enabled'],
            'collection_enabled' => $snapshot['collection_enabled'],
            'delivery_lead_time' => $snapshot['delivery_lead_time'],
            'collection_lead_time' => $snapshot['collection_lead_time'],
        ];
    }

    /** Adds $minutes to both lead times (0 = back to normal). */
    public function setBusy(Location $location, int $minutes): void
    {
        if (!in_array($minutes, self::BUSY_STEPS, true)) {
            throw new \InvalidArgumentException('Unsupported busy minutes: '.$minutes);
        }

        $location = $this->fresh($location);
        $current = [
            'delivery' => (int) $location->getSettings('delivery.lead_time', 15),
            'collection' => (int) $location->getSettings('collection.lead_time', 15),
        ];
        // The normal lead times are decided under the row lock, so two changes at once never save
        // already raised times as the normal ones.
        $base = null;
        $this->update($location, self::SETTINGS, function (array $data) use ($minutes, $current, &$base) {
            $base = $data['busy_base'] ?? $current;
            $data['busy_minutes'] = $minutes;
            $data['busy_base'] = $minutes > 0 ? $base : null;

            return $data;
        });

        foreach (self::ORDER_TYPES as $type) {
            $this->write($location, $type, ['lead_time' => (int) $base[$type] + $minutes]);
        }

        StoreChangeNotifier::notify($location, 'store.changed');
    }

    /** Pauses new orders until the end of today, or reopens. */
    public function setPaused(Location $location, bool $paused): void
    {
        $this->write($location, self::SETTINGS, [
            'closed_until' => $paused ? now()->endOfDay()->toIso8601String() : null,
        ]);

        StoreChangeNotifier::notify($location, 'store.changed');
    }

    public function setOrderType(Location $location, string $type, bool $enabled): void
    {
        if (!in_array($type, self::ORDER_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported order type: '.$type);
        }

        $this->write($location, $type, ['is_enabled' => $enabled ? 1 : 0]);

        StoreChangeNotifier::notify($location, 'store.changed');
    }

    /** The location's own `voxpilot` settings. */
    public function own(Location $location): array
    {
        return (array) $location->getSettings(self::SETTINGS, []);
    }

    /** Merges $values into one settings item of the location (the other keys of the item are kept). */
    public function write(Location $location, string $item, array $values): void
    {
        $this->update($location, $item, fn (array $data) => array_merge($data, $values));
    }

    /**
     * Read-modify-write of one settings item under a row lock, so two staff members changing it at
     * once do not lose each other's change. Written on the table directly: LocationSettings' own
     * save path depends on model hooks.
     *
     * @param callable(array): array $change
     */
    public function update(Location $location, string $item, callable $change): void
    {
        $table = (new LocationSettings())->getTable();
        $key = ['location_id' => $location->getKey(), 'item' => $item];

        DB::transaction(function () use ($table, $key, $change): void {
            $current = DB::table($table)->where($key)->lockForUpdate()->value('data');
            $data = $change((array) (json_decode((string) $current, true) ?: []));
            DB::table($table)->updateOrInsert($key, ['data' => json_encode($data)]);
        });

        LocationSettings::clearInternalCache();
        $location->unsetRelation('settings');
    }

    protected function fresh(Location $location): Location
    {
        $location->unsetRelation('settings');

        return $location;
    }

    /** Whether the opening hours say open right now (null when they cannot be read). */
    protected function scheduleOpen(Location $location): ?bool
    {
        try {
            return $location->newWorkingSchedule('opening')->isOpen();
        } catch (\Throwable) {
            return null;
        }
    }
}
