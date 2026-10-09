<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Stock;
use Igniter\Local\Models\Location;
use Illuminate\Support\Collection;

/**
 * Sold out with one click: TastyIgniter's stock override on the item's stock at the location.
 *
 * TastyIgniter only applies the override to tracked stock. When the owner does not track an item,
 * tracking is switched on for the override and switched off again when the item is back, so the
 * owner's stock settings end where they started (the stocks switched on here are kept in the
 * location's `voxpilot` settings).
 */
class MenuAvailability
{
    public function __construct(protected StoreStatus $store = new StoreStatus()) {}

    /** The location's enabled items, sold-out ones flagged. */
    public function items(Location $location): Collection
    {
        return $this->query($location)
            ->with(['stocks', 'categories'])
            ->orderBy('menu_name')
            ->get()
            ->map(fn (Menu $menu) => [
                'id' => (int) $menu->getKey(),
                'name' => (string) $menu->menu_name,
                'category' => (string) ($menu->categories->first()?->name ?? ''),
                'price' => (float) $menu->menu_price,
                'sold_out' => $this->isSoldOut($menu, $location),
            ]);
    }

    public function isSoldOut(Menu $menu, Location $location): bool
    {
        return $menu->stocks->where('location_id', $location->getKey())
            ->contains(fn (Stock $stock) => $stock->outOfStock());
    }

    public function setSoldOut(Location $location, int $menuId, bool $soldOut): Menu
    {
        /** @var Menu $menu */
        $menu = $this->query($location)->findOrFail($menuId);
        $stock = $menu->getStockByLocation($location);
        $switchedOn = array_map('intval', (array) ($this->store->own($location)['tracked_for_sold_out'] ?? []));

        if ($soldOut) {
            if (!$stock->is_tracked) {
                $stock->is_tracked = true;
                $stock->saveQuietly();
                $switchedOn[] = (int) $stock->getKey();
            }
            $stock->applyOutOfStockOverride(Stock::OOS_INDEFINITELY);
        } else {
            $stock->clearOutOfStockOverride();
            if (in_array((int) $stock->getKey(), $switchedOn, true)) {
                $stock->is_tracked = false;
                $stock->saveQuietly();
                $switchedOn = array_diff($switchedOn, [(int) $stock->getKey()]);
            }
        }

        $this->store->write($location, StoreStatus::SETTINGS, ['tracked_for_sold_out' => array_values(array_unique($switchedOn))]);

        return $menu->unsetRelation('stocks');
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
