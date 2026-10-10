<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Console;

use Igniter\Cart\Models\Category;
use Igniter\VoxPilot\Services\MenuAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives every restaurant its own copy of the menu rows it shares with another restaurant (a dish,
 * category or option attached to locations of several restaurants, as a demo seed or an old
 * single-restaurant setup leaves them). Shared, an edit or a "not available today" in one restaurant
 * changed the other's. The restaurant with the lowest id keeps the original rows; each other one gets
 * copies with its locations, prices, sizes, categories, stock and availability marks.
 */
class SplitSharedMenus extends Command
{
    protected $signature = 'voxpilot:split-shared-menus {--dry-run : Only list what is shared}';

    protected $description = 'Give each restaurant its own copy of menu rows shared with other restaurants';

    /** location_id => tenant_id */
    protected array $tenantOf = [];

    /** [type][old id][tenant id] => new id */
    protected array $copies = [];

    /** [tenant id][old option value id] => new option value id */
    protected array $optionValues = [];

    public function handle(): int
    {
        $this->tenantOf = DB::table('locations')->whereNotNull('tenant_id')->pluck('tenant_id', 'location_id')
            ->map(fn ($id) => (int) $id)->all();

        $shared = [];
        foreach (['categories', 'menu_options', 'menus'] as $type) {
            $shared[$type] = $this->sharedRows($type);
            $this->line(sprintf('%s shared by several restaurants: %d', $type, count($shared[$type])));
        }
        if ($this->option('dry-run') || !array_filter($shared)) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($shared): void {
            foreach ($shared['categories'] as $id => $tenants) {
                foreach (array_slice($tenants, 1) as $tenantId) {
                    $this->copies['categories'][$id][$tenantId] = $this->copyCategory($id, $tenantId);
                }
            }
            foreach ($shared['menu_options'] as $id => $tenants) {
                foreach (array_slice($tenants, 1) as $tenantId) {
                    $this->copies['menu_options'][$id][$tenantId] = $this->copyOption($id, $tenantId);
                }
            }
            foreach ($shared['menus'] as $id => $tenants) {
                foreach (array_slice($tenants, 1) as $tenantId) {
                    $this->copies['menus'][$id][$tenantId] = $this->copyMenu($id, $tenantId);
                }
            }
        });

        if (!empty($this->copies['categories'])) {
            Category::withoutGlobalScopes()->fixTree();
        }
        $this->info('Done: every restaurant has its own menu rows.');

        return self::SUCCESS;
    }

    /** @return array<int, int[]> row id => its restaurants (lowest id first), for rows with several */
    protected function sharedRows(string $type): array
    {
        $rows = [];
        foreach (DB::table('locationables')->where('locationable_type', $type)->get(['locationable_id', 'location_id']) as $link) {
            if ($tenantId = $this->tenantOf[$link->location_id] ?? null) {
                $rows[(int) $link->locationable_id][$tenantId] = $tenantId;
            }
        }

        return collect($rows)->filter(fn ($tenants) => count($tenants) > 1)->map(function ($tenants) {
            sort($tenants);

            return array_values($tenants);
        })->all();
    }

    protected function tenantLocations(int $tenantId): array
    {
        return array_keys(array_filter($this->tenantOf, fn ($id) => $id === $tenantId));
    }

    /** Inserts a copy of a row (without its key) and returns the new key. */
    protected function copyRow(string $table, string $key, int $id, array $changes = []): int
    {
        $row = (array) DB::table($table)->where($key, $id)->first();
        unset($row[$key]);

        return (int) DB::table($table)->insertGetId(array_merge($row, $changes), $key);
    }

    /** The tenant's locations now point at the copy instead of the original. */
    protected function moveLocations(string $type, int $from, int $to, int $tenantId): void
    {
        DB::table('locationables')
            ->where('locationable_type', $type)
            ->where('locationable_id', $from)
            ->whereIn('location_id', $this->tenantLocations($tenantId))
            ->update(['locationable_id' => $to]);
    }

    protected function copyCategory(int $id, int $tenantId): int
    {
        $original = DB::table('categories')->where('category_id', $id)->first();
        $parent = $original->parent_id ? ($this->copies['categories'][$original->parent_id][$tenantId] ?? $original->parent_id) : null;
        $copy = $this->copyRow('categories', 'category_id', $id, [
            'parent_id' => $parent,
            'permalink_slug' => $original->permalink_slug ? $original->permalink_slug.'-'.$tenantId : null,
        ]);
        $this->moveLocations('categories', $id, $copy, $tenantId);
        $this->copyMedia('categories', $id, $copy);

        return $copy;
    }

    protected function copyOption(int $id, int $tenantId): int
    {
        $copy = $this->copyRow('menu_options', 'option_id', $id);
        foreach (DB::table('menu_option_values')->where('option_id', $id)->pluck('option_value_id') as $valueId) {
            $this->optionValues[$tenantId][(int) $valueId] = $this->copyRow('menu_option_values', 'option_value_id', (int) $valueId, ['option_id' => $copy]);
        }
        $this->moveLocations('menu_options', $id, $copy, $tenantId);

        return $copy;
    }

    protected function copyMenu(int $id, int $tenantId): int
    {
        $copy = $this->copyRow('menus', 'menu_id', $id);
        $this->moveLocations('menus', $id, $copy, $tenantId);

        foreach (DB::table('menu_categories')->where('menu_id', $id)->pluck('category_id') as $categoryId) {
            DB::table('menu_categories')->insert([
                'menu_id' => $copy,
                'category_id' => $this->copies['categories'][$categoryId][$tenantId] ?? $categoryId,
            ]);
        }
        foreach (DB::table('menu_mealtimes')->where('menu_id', $id)->pluck('mealtime_id') as $mealtimeId) {
            DB::table('menu_mealtimes')->insert(['menu_id' => $copy, 'mealtime_id' => $mealtimeId]);
        }
        foreach (DB::table('ingredientables')->where('ingredientable_type', 'menus')->where('ingredientable_id', $id)->get() as $link) {
            DB::table('ingredientables')->insert(['ingredient_id' => $link->ingredient_id, 'ingredientable_type' => 'menus', 'ingredientable_id' => $copy]);
        }

        // Sizes and other options, with their prices.
        foreach (DB::table('menu_item_options')->where('menu_id', $id)->get() as $itemOption) {
            $newItemOption = $this->copyRow('menu_item_options', 'menu_option_id', (int) $itemOption->menu_option_id, [
                'menu_id' => $copy,
                'option_id' => $this->copies['menu_options'][$itemOption->option_id][$tenantId] ?? $itemOption->option_id,
            ]);
            foreach (DB::table('menu_item_option_values')->where('menu_option_id', $itemOption->menu_option_id)->pluck('menu_option_value_id') as $valueId) {
                $value = DB::table('menu_item_option_values')->where('menu_option_value_id', $valueId)->first();
                $newValue = $this->copyRow('menu_item_option_values', 'menu_option_value_id', (int) $valueId, [
                    'menu_option_id' => $newItemOption,
                    'option_value_id' => $this->optionValues[$tenantId][$value->option_value_id] ?? $value->option_value_id,
                ]);
                foreach (DB::table('menu_item_option_linked_values')->where('menu_item_option_value_id', $valueId)->pluck('menu_option_id') as $linked) {
                    DB::table('menu_item_option_linked_values')->insert(['menu_option_id' => $linked, 'menu_item_option_value_id' => $newValue]);
                }
            }
        }

        if ($special = DB::table('menus_specials')->where('menu_id', $id)->value('special_id')) {
            $this->copyRow('menus_specials', 'special_id', (int) $special, ['menu_id' => $copy]);
        }
        $this->copyMedia('menus', $id, $copy);

        // Stock and "not available today" marks are per location: the tenant's follow the copy.
        DB::table('stocks')->where('stockable_type', 'menus')->where('stockable_id', $id)
            ->whereIn('location_id', $this->tenantLocations($tenantId))
            ->update(['stockable_id' => $copy]);
        foreach ($this->tenantLocations($tenantId) as $locationId) {
            $this->moveAvailabilityMark($locationId, $id, $copy);
        }

        return $copy;
    }

    protected function copyMedia(string $type, int $from, int $to): void
    {
        foreach (DB::table('media_attachments')->where('attachment_type', $type)->where('attachment_id', $from)->pluck('id') as $mediaId) {
            $this->copyRow('media_attachments', 'id', (int) $mediaId, ['attachment_id' => $to]);
        }
    }

    protected function moveAvailabilityMark(int $locationId, int $from, int $to): void
    {
        $setting = DB::table('location_settings')->where('location_id', $locationId)->where('item', 'voxpilot')->first();
        $data = $setting ? (array) json_decode((string) $setting->data, true) : [];
        $marks = (array) ($data[MenuAvailability::KEY] ?? []);
        if (!array_key_exists((string) $from, $marks) && !array_key_exists($from, $marks)) {
            return;
        }
        $marks[$to] = $marks[$from] ?? $marks[(string) $from];
        unset($marks[$from], $marks[(string) $from]);
        $data[MenuAvailability::KEY] = $marks;
        DB::table('location_settings')->where('id', $setting->id)->update(['data' => json_encode($data)]);
    }
}
