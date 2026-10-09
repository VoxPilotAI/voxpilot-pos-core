<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers\Concerns;

use Igniter\Admin\Models\Status;
use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Support\Collection;

/**
 * Quick actions on an order from the board and the live screen: a modal with the order's details,
 * buttons for every status (the next one first), cancel, print and a link to the full order.
 * Orders are read through the tenant-scoped Order model, so an owner only reaches their own
 * restaurant's orders; status changes go through TastyIgniter's status history (VoxPilot is
 * notified the same way as from the order page).
 */
trait OrderQuickActions
{
    public function onOpenOrder(): array
    {
        return ['#vp-order-modal-content' => $this->renderOrderModal($this->findQuickOrder())];
    }

    public function onSetOrderStatus(): array
    {
        $order = $this->findQuickOrder();
        $target = request()->input('status_id') === 'next'
            ? $this->nextStatusId($order)
            : (int) request()->input('status_id');

        $allowed = $this->quickStatuses()->pluck('status_id')->map(fn ($id) => (int) $id)->all();
        if ($target && in_array($target, $allowed, true) && (int) $order->status_id !== $target) {
            $order->updateOrderStatus($target, ['staff_id' => $this->getUser()?->getKey()]);
            $order->refresh();
        }

        return array_merge(
            ['#vp-order-modal-content' => $this->renderOrderModal($order)],
            $this->afterQuickStatusChange($order),
        );
    }

    /** Extra partial updates after a status change (the board re-renders its columns). */
    protected function afterQuickStatusChange(Order $order): array
    {
        return ['#vp-ticket-status-'.$order->order_id => $this->renderStatusPill($order)];
    }

    protected function findQuickOrder(): Order
    {
        return Order::query()->with(['menus', 'status', 'address', 'payment_method'])
            ->findOrFail((int) request()->input('order_id'));
    }

    /** Every order status, canceled included (offered separately, with a confirmation). */
    protected function quickStatuses(): Collection
    {
        return Status::query()->where('status_for', 'order')->orderBy('status_id')->get();
    }

    protected function canceledStatusId(): ?int
    {
        $id = (int) setting('canceled_order_status');
        if ($id) {
            return $id;
        }
        $status = $this->quickStatuses()->first(fn ($s) => strcasecmp((string) $s->status_name, 'Canceled') === 0);

        return $status ? (int) $status->status_id : null;
    }

    protected function nextStatusId(Order $order): ?int
    {
        $flow = $this->quickStatuses()->reject(fn ($s) => (int) $s->status_id === $this->canceledStatusId())->values();
        $index = $flow->search(fn ($s) => (int) $s->status_id === (int) $order->status_id);
        $next = $index === false ? $flow->first() : $flow->get($index + 1);

        return $next ? (int) $next->status_id : null;
    }

    /** TastyIgniter's default status names in the admin's language; custom names as they are. */
    public static function statusLabel(?string $name): string
    {
        $key = 'igniter.voxpilot::orders.status_'.strtolower(str_replace(' ', '_', (string) $name));

        return lang($key) !== $key ? lang($key) : (string) $name;
    }

    protected function renderStatusPill(Order $order): string
    {
        return $this->makePartial('orderstatuspill', ['order' => $order]);
    }

    protected function renderOrderModal(Order $order): string
    {
        $canceled = $this->canceledStatusId();
        $next = $this->nextStatusId($order);

        return $this->makePartial('ordermodal', [
            'order' => $order,
            'phone' => VoxPilotOrderMetadata::where('order_id', $order->order_id)->exists(),
            'statuses' => $this->quickStatuses()->reject(fn ($s) => (int) $s->status_id === $canceled)->values(),
            'nextStatus' => $next ? $this->quickStatuses()->firstWhere('status_id', $next) : null,
            'canceledId' => $canceled,
            'isCanceled' => $canceled && (int) $order->status_id === $canceled,
        ]);
    }
}
