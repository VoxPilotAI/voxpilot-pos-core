<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Cart\Models\MenuItemOptionValue;
use Illuminate\Support\Collection;

class MenuMatcher
{
    /** @var Collection<int, Menu> */
    protected Collection $menus;

    protected int $locationId;

    public function __construct(int $locationId)
    {
        $this->locationId = $locationId;
        $this->menus = $this->loadMenus();
    }

    /**
     * @return array{matched: array, unmapped: array}
     */
    public function matchItems(array $items): array
    {
        $matched = [];
        $unmapped = [];

        foreach ($items as $item) {
            $result = $this->matchSingle($item);
            if ($result['menu']) {
                $matched[] = $result;
            } else {
                $unmapped[] = $item;
            }
        }

        return ['matched' => $matched, 'unmapped' => $unmapped];
    }

    protected function matchSingle(array $item): array
    {
        $name = $item['name'] ?? '';
        $menu = $this->findMenu($name);

        if (!$menu) {
            return ['menu' => null, 'item' => $item];
        }

        $basePrice = (float) $menu->getBuyablePrice();
        $optionResult = $this->matchSizeOption($menu, $item['size'] ?? null);

        $unitPrice = $basePrice + ($optionResult['price'] ?? 0);
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $lineTotal = $unitPrice * $quantity;

        $callerPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
        $callerPriceCents = isset($item['unitPriceCents']) ? (int) $item['unitPriceCents'] : null;
        $callerUnitPrice = $callerPrice ?? ($callerPriceCents !== null ? $callerPriceCents / 100 : null);

        return [
            'menu' => $menu,
            'menu_id' => $menu->menu_id,
            'name' => $menu->menu_name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'base_price' => $basePrice,
            'line_total' => $lineTotal,
            'option' => $optionResult,
            'caller_unit_price' => $callerUnitPrice,
            'price_mismatch' => $callerUnitPrice !== null && abs($callerUnitPrice - $unitPrice) > 0.01,
            'item' => $item,
        ];
    }

    protected function findMenu(string $name): ?Menu
    {
        $normalized = $this->normalize($name);

        foreach ($this->menus as $menu) {
            if ($this->normalize($menu->menu_name) === $normalized) {
                return $menu;
            }
        }

        // Partial match: caller name contains menu name or vice versa
        foreach ($this->menus as $menu) {
            $menuNorm = $this->normalize($menu->menu_name);
            if (str_contains($normalized, $menuNorm) || str_contains($menuNorm, $normalized)) {
                return $menu;
            }
        }

        return null;
    }

    protected function matchSizeOption(Menu $menu, ?string $size): array
    {
        if (!$size) {
            return ['matched' => false, 'name' => null, 'price' => 0, 'option_value_id' => null];
        }

        $normalizedSize = $this->normalize($size);

        $menuItemOptions = MenuItemOption::where('menu_id', $menu->menu_id)
            ->with(['option.option_values', 'menu_option_values'])
            ->get();

        foreach ($menuItemOptions as $mio) {
            $optionValues = $mio->option?->option_values ?? collect();
            foreach ($mio->menu_option_values as $miov) {
                $optionValue = $optionValues->firstWhere('option_value_id', $miov->option_value_id);
                $valueName = $optionValue?->name ?? '';
                if ($this->normalize($valueName) === $normalizedSize) {
                    $price = $miov->override_price !== null
                        ? (float) $miov->override_price
                        : (float) ($optionValue->price ?? 0);

                    return [
                        'matched' => true,
                        'name' => $valueName,
                        'price' => $price,
                        'option_value_id' => $miov->option_value_id,
                        'menu_option_id' => $mio->menu_option_id,
                    ];
                }
            }
        }

        return ['matched' => false, 'name' => $size, 'price' => 0, 'option_value_id' => null];
    }

    protected function loadMenus(): Collection
    {
        $table = (new \Igniter\Local\Models\Location())->getTable();

        return Menu::withoutGlobalScopes()
            ->whereHas('locations', function ($q) use ($table) {
                $q->withoutGlobalScopes()
                    ->where($table . '.location_id', $this->locationId);
            })
            ->where('menu_status', true)
            ->get();
    }

    protected function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text;
        $text = preg_replace('/\pM/u', '', $text) ?? $text;
        return trim($text);
    }
}
