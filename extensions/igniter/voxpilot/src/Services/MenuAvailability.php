<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Carbon\Carbon;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Stock;
use Igniter\Local\Models\Location;
use Illuminate\Support\Collection;

/**
 * Which menu items the location can sell right now.
 *
 * A restaurant mostly cooks to order: a pizza has no stock count, it stops being available when an
 * ingredient runs out. So the staff mark an item "not available today" with one click, and it is
 * back on its own the next day; nothing in TastyIgniter's stock (quantities, tracking) is touched.
 * The marks live in the location's `voxpilot` settings (`unavailable_until`: menu id => end of day).
 * Items whose stock the owner does track in TastyIgniter (bottled drinks…) are also unavailable when
 * that stock runs out.
 */
class MenuAvailability
{
    public const KEY = 'unavailable_until';

    public function __construct(protected StoreStatus $store = new StoreStatus()) {}

    /** The location's enabled items with their availability. */
    public function items(Location $location): Collection
    {
        $marked = $this->unavailableToday($location);

        return $this->query($location)
            ->with(['stocks', 'categories'])
            ->orderBy('menu_name')
            ->get()
            ->map(function (Menu $menu) use ($location, $marked) {
                $outOfStock = $this->outOfStock($menu, $location);
                $today = in_array((int) $menu->getKey(), $marked, true);

                return [
                    'id' => (int) $menu->getKey(),
                    'name' => (string) $menu->menu_name,
                    'category' => (string) ($menu->categories->first()?->name ?? ''),
                    'price' => (float) $menu->menu_price,
                    'unavailable_today' => $today,
                    'out_of_stock' => $outOfStock,
                    'sold_out' => $today || $outOfStock,
                ];
            });
    }

    /** Ids of the items marked not available today (expired marks left out). */
    public function unavailableToday(Location $location): array
    {
        $now = now();
        $marks = (array) ($this->store->own($location)[self::KEY] ?? []);

        return array_values(array_map('intval', array_keys(array_filter(
            $marks,
            fn ($until) => is_string($until) && Carbon::parse($until)->greaterThan($now),
        ))));
    }

    /** Marks an item of the location not available until the end of today, or available again. */
    public function setUnavailableToday(Location $location, int $menuId, bool $unavailable): Menu
    {
        /** @var Menu $menu */
        $menu = $this->query($location)->findOrFail($menuId);
        $now = now();

        $this->store->update($location, StoreStatus::SETTINGS, function (array $data) use ($menuId, $unavailable, $now) {
            // Drop expired marks while here, so the list never grows.
            $marks = array_filter(
                (array) ($data[self::KEY] ?? []),
                fn ($until) => is_string($until) && Carbon::parse($until)->greaterThan($now),
            );
            if ($unavailable) {
                $marks[(string) $menuId] = $now->copy()->endOfDay()->toIso8601String();
            } else {
                unset($marks[(string) $menuId]);
            }
            $data[self::KEY] = (object) $marks;

            return $data;
        });

        return $menu;
    }

    /** Out of stock in TastyIgniter's own inventory (only items whose stock the owner tracks). */
    protected function outOfStock(Menu $menu, Location $location): bool
    {
        return $menu->stocks->where('location_id', $location->getKey())
            ->contains(fn (Stock $stock) => $stock->outOfStock());
    }

    /** Enabled items offered at the location (same rule as the menu VoxPilot reads). */
    protected function query(Location $location)
    {
        $table = (new Location())->getTable();

        return Menu::withoutGlobalScopes()
            ->whereHas('locations', fn ($q) => $q->withoutGlobalScopes()->where($table.'.location_id', $location->getKey()))
            ->where('menu_status', true);
    }
}
