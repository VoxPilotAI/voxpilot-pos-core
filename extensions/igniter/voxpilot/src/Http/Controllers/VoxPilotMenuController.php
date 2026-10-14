<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Services\OrderIngestionService;
use Igniter\VoxPilot\Services\StoreStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VoxPilotMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('voxpilot_tenant');
        $token = $request->attributes->get('voxpilot_token');

        $locationId = (new OrderIngestionService())->resolveLocationId(
            $request->all(),
            $tenant,
            $token,
        );

        $locationTable = (new \Igniter\Local\Models\Location())->getTable();

        $menus = Menu::withoutGlobalScopes()
            ->whereHas('locations', function ($q) use ($locationTable, $locationId) {
                $q->withoutGlobalScopes()
                    ->where($locationTable . '.location_id', $locationId);
            })
            ->where('menu_status', true)
            ->with(['categories', 'mealtimes', 'ingredients', 'stocks'])
            ->get();

        // What the restaurant can sell right now: items marked not available today, out of tracked
        // stock, outside their mealtime or with a disabled ingredient are listed apart, so the
        // assistant can say "not today" instead of offering them (pos-gateway SPEC-001).
        $location = Location::query()->withoutGlobalScopes()->find($locationId);
        $marked = $location ? (new MenuAvailability())->unavailableToday($location) : [];
        $unavailable = [];
        $menus = $menus->filter(function (Menu $menu) use ($locationId, $marked, &$unavailable) {
            $reason = match (true) {
                in_array((int) $menu->menu_id, $marked, true) => 'not_available_today',
                $menu->outOfStock($locationId) => 'out_of_stock',
                !$menu->isAvailable() => 'not_available_now',
                default => null,
            };
            if ($reason) {
                $unavailable[] = ['menu_id' => (int) $menu->menu_id, 'name' => (string) $menu->menu_name, 'reason' => $reason];
            }

            return $reason === null;
        });

        $items = $menus->map(function (Menu $menu) {
            $basePrice = (float) $menu->getBuyablePrice();

            $options = MenuItemOption::where('menu_id', $menu->menu_id)
                ->with(['option.option_values', 'menu_option_values'])
                ->get();

            $sizes = [];
            $modifiers = [];

            foreach ($options as $mio) {
                $optionName = $mio->option?->option_name ?? '';
                $optionValues = $mio->option?->option_values ?? collect();
                $isRequired = (bool) $mio->required;

                $values = [];
                foreach ($mio->menu_option_values as $miov) {
                    $ov = $optionValues->firstWhere('option_value_id', $miov->option_value_id);
                    $price = $miov->override_price !== null
                        ? (float) $miov->override_price
                        : (float) ($ov->price ?? 0);

                    $values[] = [
                        'name' => $ov->name ?? '',
                        'price' => $price,
                    ];
                }

                $entry = [
                    'name' => $optionName,
                    'required' => $isRequired,
                    'values' => $values,
                ];

                if ($isRequired && count($values) > 1) {
                    $sizes[] = $entry;
                } else {
                    $modifiers[] = $entry;
                }
            }

            $categories = ($menu->categories ?? collect())->map(fn($c) => $c->name)->values()->toArray();

            return [
                'menu_id' => $menu->menu_id,
                'name' => $menu->menu_name,
                'description' => $menu->menu_description ?? '',
                'base_price' => $basePrice,
                'categories' => $categories,
                'sizes' => $sizes,
                'modifiers' => $modifiers,
            ];
        });

        return response()->json([
            'data' => [
                'location_id' => $locationId,
                'items_count' => $items->count(),
                'items' => $items->values(),
                'unavailable' => $unavailable,
                'store' => $location ? (new StoreStatus())->forVoxPilot($location) : null,
            ],
        ]);
    }
}
