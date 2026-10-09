<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers\Concerns;

use Igniter\Flame\Exception\FlashException;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Services\ManualOrderService;
use Igniter\VoxPilot\Services\MenuAvailability;
use Igniter\VoxPilot\Services\StoreStatus;

/**
 * What the restaurant takes right now, from the board: kitchen busy (+minutes), pause orders for the
 * rest of the day, delivery and pickup on or off, sold-out items and a quick manual order. Every
 * handler works on the owner's location (StoreStatus::resolveLocation, tenant-scoped).
 */
trait StoreControls
{
    protected ?Location $storeLocationCache = null;

    public function onSetStoreBusy(): array
    {
        $minutes = (int) request()->input('minutes');
        if (in_array($minutes, StoreStatus::BUSY_STEPS, true)) {
            $this->storeStatus()->setBusy($this->storeLocation(), $minutes);
        }

        return $this->renderStoreBar();
    }

    public function onSetStorePaused(): array
    {
        $this->storeStatus()->setPaused($this->storeLocation(), (bool) request()->input('paused'));

        return $this->renderStoreBar();
    }

    public function onSetStoreOrderType(): array
    {
        $type = (string) request()->input('type');
        if (in_array($type, StoreStatus::ORDER_TYPES, true)) {
            $this->storeStatus()->setOrderType($this->storeLocation(), $type, (bool) request()->input('enabled'));
        }

        return $this->renderStoreBar();
    }

    public function onOpenAvailability(): array
    {
        return ['#vp-order-modal-content' => $this->renderAvailability()];
    }

    public function onSetSoldOut(): array
    {
        (new MenuAvailability($this->storeStatus()))->setSoldOut(
            $this->storeLocation(),
            (int) request()->input('menu_id'),
            (bool) request()->input('sold_out'),
        );

        return array_merge(['#vp-order-modal-content' => $this->renderAvailability()], $this->renderStoreBar());
    }

    public function onOpenManualOrder(): array
    {
        return ['#vp-order-modal-content' => $this->makePartial('manualorder', [
            'items' => (new MenuAvailability($this->storeStatus()))->items($this->storeLocation()),
            'store' => $this->storeStatus()->snapshot($this->storeLocation()),
        ])];
    }

    public function onCreateManualOrder(): array
    {
        $order = (new ManualOrderService())->create($this->storeLocation(), [
            'customer_name' => request()->input('customer_name'),
            'telephone' => request()->input('telephone'),
            'type' => request()->input('type'),
            'address' => request()->input('address'),
            'notes' => request()->input('notes'),
            'items' => array_values(array_filter(
                (array) request()->input('items', []),
                fn ($line) => is_array($line) && !empty($line['menu_id']),
            )),
        ]);

        flash()->success(lang('igniter.voxpilot::orders.manual_created', ['id' => $order->order_id]));

        return array_merge(
            ['#vp-order-modal-content' => $this->renderOrderModal($order->fresh(['menus', 'status', 'address', 'payment_method', 'assignee']))],
            $this->afterQuickStatusChange($order),
        );
    }

    protected function storeLocation(): Location
    {
        return $this->storeLocationCache ??= StoreStatus::resolveLocation()
            ?? throw new FlashException(lang('igniter.voxpilot::board.no_location'));
    }

    protected function storeStatus(): StoreStatus
    {
        return new StoreStatus();
    }

    protected function storeBarVars(): array
    {
        $location = StoreStatus::resolveLocation();
        if (!$location) {
            return ['store' => null, 'soldOut' => 0];
        }

        return [
            'store' => $this->storeStatus()->snapshot($location),
            'soldOut' => (new MenuAvailability($this->storeStatus()))->items($location)->where('sold_out', true)->count(),
        ];
    }

    protected function renderStoreBar(): array
    {
        return ['#vp-store-bar' => $this->makePartial('storebar', $this->storeBarVars())];
    }

    protected function renderAvailability(): string
    {
        return $this->makePartial('availability', [
            'items' => (new MenuAvailability($this->storeStatus()))->items($this->storeLocation()),
            'locationName' => (string) $this->storeLocation()->location_name,
        ]);
    }
}
