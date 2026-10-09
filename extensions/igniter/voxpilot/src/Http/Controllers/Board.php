<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\Template;
use Igniter\Admin\Models\Status;
use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Support\Collection;

/**
 * Orders board: one column per order status, the active orders of the owner's restaurant (orders
 * go through the tenant-scoped Order model), and a button on each card that moves the order to the
 * next status. Status changes go through TastyIgniter's status history, so VoxPilot is notified
 * the same way as from the order page.
 */
class Board extends AdminController
{
    protected null|string|array $requiredPermissions = ['Admin.Orders'];

    /** Completed orders kept on the board (latest first). */
    private const COMPLETED_ON_BOARD = 8;

    /** Active orders older than this are left to the orders list. */
    private const ACTIVE_DAYS = 2;

    public function index(): void
    {
        Template::setTitle($this->pageTitle = lang('igniter.voxpilot::board.title'));
        $this->vars['columns'] = $this->columns();
    }

    public function onMoveOrder(): array
    {
        $orderId = (int) request()->input('order_id');
        $statusId = (int) request()->input('status_id');

        /** @var Order|null $order */
        $order = Order::query()->find($orderId);
        $allowed = $this->statuses()->pluck('status_id')->map(fn ($id) => (int) $id)->all();
        if ($order && in_array($statusId, $allowed, true) && (int) $order->status_id !== $statusId) {
            $order->updateOrderStatus($statusId, ['staff_id' => $this->getUser()?->getKey()]);
        }

        return ['#vp-board' => $this->makePartial('columns', ['columns' => $this->columns()])];
    }

    public function onRefresh(): array
    {
        return ['#vp-board' => $this->makePartial('columns', ['columns' => $this->columns()])];
    }

    /** Order statuses shown as columns: every order status except canceled, in their order. */
    protected function statuses(): Collection
    {
        $canceled = (int) setting('canceled_order_status');

        return Status::query()
            ->where('status_for', 'order')
            ->orderBy('status_id')
            ->get()
            ->reject(fn ($s) => $canceled ? (int) $s->status_id === $canceled : strcasecmp((string) $s->status_name, 'Canceled') === 0)
            ->values();
    }

    protected function completedIds(Collection $statuses): array
    {
        $completed = array_map('intval', (array) setting('completed_order_status', []));
        if ($completed) {
            return $completed;
        }

        return $statuses->filter(fn ($s) => strcasecmp((string) $s->status_name, 'Completed') === 0)
            ->pluck('status_id')->map(fn ($id) => (int) $id)->all();
    }

    protected function columns(): array
    {
        $statuses = $this->statuses();
        $completed = $this->completedIds($statuses);

        $active = Order::query()
            ->with('menus')
            ->whereIn('status_id', $statuses->pluck('status_id')->diff($completed)->all())
            ->where('created_at', '>=', now()->subDays(self::ACTIVE_DAYS))
            ->orderBy('created_at')
            ->get();
        $done = Order::query()
            ->with('menus')
            ->whereIn('status_id', $completed ?: [0])
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('created_at')
            ->limit(self::COMPLETED_ON_BOARD)
            ->get();
        $orders = $active->concat($done);

        $phoneIds = array_flip(VoxPilotOrderMetadata::whereIn('order_id', $orders->pluck('order_id'))->pluck('order_id')->all());

        $columns = [];
        foreach ($statuses as $index => $status) {
            $next = $statuses[$index + 1] ?? null;
            $cards = $orders->where('status_id', $status->status_id)->map(fn (Order $order) => [
                'id' => (int) $order->order_id,
                'customer' => trim($order->first_name.' '.$order->last_name) ?: '—',
                'phone' => isset($phoneIds[$order->order_id]),
                'type' => (string) $order->order_type_name,
                'total' => (float) $order->order_total,
                'minutes' => (int) $order->created_at?->diffInMinutes(now()),
                'lines' => $order->menus->map(fn ($m) => ['name' => (string) $m->name, 'quantity' => (int) $m->quantity])->all(),
                'comment' => (string) ($order->comment ?? ''),
            ])->values()->all();

            $columns[] = [
                'id' => (int) $status->status_id,
                'name' => (string) $status->status_name,
                'color' => (string) ($status->status_color ?: '#8a96b4'),
                'done' => in_array((int) $status->status_id, $completed, true),
                'next' => $next && !in_array((int) $status->status_id, $completed, true)
                    ? ['id' => (int) $next->status_id, 'name' => (string) $next->status_name]
                    : null,
                'cards' => $cards,
            ];
        }

        return $columns;
    }
}
