<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Quick manual order from the board (walk-in or a call the staff took): the same order, lines and
 * totals as a VoxPilot order, without VoxPilot metadata, so it is not counted as an AI phone order
 * and no status changes are sent to VoxPilot.
 */
class ManualOrderService extends OrderIngestionService
{
    public const MAX_LINES = 30;

    /**
     * @param array{customer_name?: string, telephone?: string, type?: string, address?: string,
     *     notes?: string, items?: array<int, array{menu_id: int|string, quantity: int|string}>} $input
     */
    public function create(Location $location, array $input): Order
    {
        $data = Validator::make($input, [
            'customer_name' => ['nullable', 'string', 'max:96'],
            'telephone' => ['nullable', 'string', 'max:32'],
            'type' => ['required', 'in:pickup,delivery'],
            'address' => ['nullable', 'required_if:type,delivery', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ])->validate();

        $menus = $this->locationMenus($location, array_column($data['items'], 'menu_id'));
        $sellable = (new MenuAvailability())->items($location)->where('sold_out', false)->pluck('id')->all();
        $matched = [];
        foreach ($data['items'] as $line) {
            /** @var Menu|null $menu */
            $menu = $menus->get((int) $line['menu_id']);
            if ($menu && !in_array((int) $menu->getKey(), $sellable, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => lang('igniter.voxpilot::orders.manual_unavailable_item', ['item' => $menu->menu_name]),
                ]);
            }
            if (!$menu) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => lang('igniter.voxpilot::orders.manual_unknown_item'),
                ]);
            }
            $price = (float) $menu->getBuyablePrice();
            $quantity = (int) $line['quantity'];
            $matched[] = [
                'menu_id' => (int) $menu->getKey(),
                'name' => (string) $menu->menu_name,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_total' => round($price * $quantity, 2),
                'item' => ['notes' => ''],
            ];
        }

        $payload = [
            'customer' => ['name' => trim((string) ($data['customer_name'] ?? '')) ?: 'Guest', 'phone' => (string) ($data['telephone'] ?? '')],
            'fulfillment' => ['type' => $data['type'], 'address' => $data['address'] ?? null],
            'notes' => $data['notes'] ?? null,
        ];
        $tenant = Tenant::findOrFail($location->tenant_id);
        $deliveryFee = $this->computeDeliveryFee($payload, (int) $location->getKey(), $matched);

        return DB::transaction(function () use ($payload, $tenant, $location, $matched, $deliveryFee) {
            $order = $this->createOrder($payload, $tenant, (int) $location->getKey(), []);
            $this->createOrderMenus($order, $matched, []);
            $this->createOrderTotals($order, $deliveryFee);

            return $order->fresh(['menus']);
        });
    }

    /** Enabled items of the location, by id. */
    protected function locationMenus(Location $location, array $ids)
    {
        $table = (new Location())->getTable();

        return Menu::withoutGlobalScopes()
            ->whereHas('locations', fn ($q) => $q->withoutGlobalScopes()->where($table.'.location_id', $location->getKey()))
            ->where('menu_status', true)
            ->whereIn('menu_id', array_map('intval', $ids))
            ->get()
            ->keyBy('menu_id');
    }
}
