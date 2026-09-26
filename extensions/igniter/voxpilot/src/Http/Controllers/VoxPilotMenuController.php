<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\VoxPilot\Services\OrderIngestionService;
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
            ->with(['categories'])
            ->get();

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
            ],
        ]);
    }
}
