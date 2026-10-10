<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers\Concerns;

use Igniter\Admin\Models\Status;
use Igniter\Cart\Models\Order;
use Igniter\PayRegister\Models\PaymentLog;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Services\VoxPilotStatusNotifier;
use Igniter\VoxPilot\Support\StatusLabel;
use Illuminate\Support\Collection;

/**
 * Quick actions on an order from the board and the live screen: a modal with the order's details,
 * the customer's earlier orders, buttons for every status (the next one first), mark as paid,
 * assign a driver, cancel, print and a link to the full order.
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

    /** Cash, card or bank transfer (SINPE and the like) taken by the staff. */
    public const PAYMENT_METHODS = ['cash', 'card', 'transfer'];

    public function onMarkOrderPaid(): array
    {
        $order = $this->findQuickOrder();
        $method = (string) request()->input('method');
        if (in_array($method, self::PAYMENT_METHODS, true) && !$order->processed && !$order->isCanceled()) {
            $order->markAsPaymentProcessed();
            $this->logStaffPayment($order, $method);
            $order->refresh();
        }

        return ['#vp-order-modal-content' => $this->renderOrderModal($order)];
    }

    /**
     * Payment log entry for a payment the staff took. Phone orders have no TastyIgniter payment
     * method (Order::logPaymentAttempt needs one), so the entry names the method the staff chose.
     */
    protected function logStaffPayment(Order $order, string $method): void
    {
        $label = lang('igniter.voxpilot::orders.pay_'.$method);
        $log = new PaymentLog();
        $log->order_id = $order->order_id;
        $log->payment_code = $order->payment_method?->code ?: 'vp_'.$method;
        $log->payment_name = $order->payment_method?->name ?: $label;
        $log->message = lang('igniter.voxpilot::orders.paid_note', ['method' => $label, 'user' => (string) ($this->getUser()?->name ?? '')]);
        $log->is_success = true;
        $log->request = ['method' => $method];
        $log->response = [];
        $log->is_refundable = false;
        $log->save();
    }

    /** Kitchen estimates the staff pick from (minutes from now). */
    public const ETA_STEPS = [10, 20, 30, 45, 60];

    /**
     * The kitchen's estimate: the order's time becomes now + minutes (TastyIgniter's order time, so
     * the admin shows it too). A VoxPilot order tells VoxPilot right away, which tells the caller
     * when they agreed to updates (pos-gateway SPEC-004).
     */
    public function onSetOrderEta(): array
    {
        $order = $this->findQuickOrder();
        $minutes = (int) request()->input('minutes');
        if (in_array($minutes, self::ETA_STEPS, true) && !$order->isCanceled()) {
            $ready = now()->addMinutes($minutes);
            $order->order_date = $ready->toDateString();
            $order->order_time = $ready->format('H:i');
            $order->order_time_is_asap = false;
            $order->save();
            VoxPilotStatusNotifier::orderChanged($order->refresh());
        }

        return array_merge(
            ['#vp-order-modal-content' => $this->renderOrderModal($order)],
            $this->afterQuickStatusChange($order),
        );
    }

    /** Assigns the order to one of the restaurant's staff (0 = nobody). */
    public function onAssignOrder(): array
    {
        $order = $this->findQuickOrder();
        $assigneeId = (int) request()->input('assignee_id');
        $assignee = $assigneeId ? $this->assignableStaff($order)->firstWhere('user_id', $assigneeId) : null;
        if ($assignee || $assigneeId === 0) {
            $order->updateAssignTo(null, $assignee, $this->getUser());
            $order->refresh();
        }

        return ['#vp-order-modal-content' => $this->renderOrderModal($order)];
    }

    /** Extra partial updates after a status change (the board re-renders its columns). */
    protected function afterQuickStatusChange(Order $order): array
    {
        return ['#vp-ticket-status-'.$order->order_id => $this->renderStatusPill($order)];
    }

    protected function findQuickOrder(): Order
    {
        return Order::query()->with(['menus', 'status', 'address', 'payment_method', 'assignee'])
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
        return StatusLabel::for($name);
    }

    /** Staff of the order's restaurant, the only ones an order can be assigned to. */
    protected function assignableStaff(Order $order): Collection
    {
        $ids = TenantMembership::where('tenant_id', $order->tenant_id)->pluck('user_id');

        return User::query()->whereIn('user_id', $ids)->where('status', true)->orderBy('name')->get();
    }

    /** The customer's other orders at this restaurant, by phone number (tenant-scoped Order model). */
    protected function customerHistory(Order $order): array
    {
        $phone = trim((string) $order->telephone);
        if ($phone === '') {
            return ['count' => 0, 'total' => 0.0, 'recent' => collect()];
        }

        $query = fn () => Order::query()->where('telephone', $phone)
            ->where('order_id', '!=', $order->order_id)
            ->where('status_id', '>', 0);

        return [
            'count' => $query()->count(),
            'total' => (float) $query()->sum('order_total'),
            'recent' => $query()->with('menus')->orderByDesc('order_id')->limit(3)->get(),
        ];
    }

    /**
     * The call's conversation: the turns VoxPilot sent at call end (JSON), or the plain text it sent
     * with the order (a summary) as one assistant turn. Null when there is none.
     *
     * @return array<int, array{role: string, content: string}>|null
     */
    public static function transcriptTurns(?string $stored): ?array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }
        $turns = json_decode($stored, true);
        if (is_array($turns) && array_is_list($turns)) {
            $turns = array_values(array_filter($turns, fn ($t) => is_array($t) && isset($t['role'], $t['content'])));

            return $turns ?: null;
        }

        return [['role' => 'assistant', 'content' => $stored]];
    }

    /** Delivery address: TastyIgniter's address, or the line VoxPilot puts in the comment. */
    public static function deliveryAddress(Order $order): ?string
    {
        if ($order->order_type !== 'delivery') {
            return null;
        }
        $address = trim(strip_tags((string) $order->formatted_address));
        if ($address !== '') {
            return $address;
        }
        foreach (preg_split('/\R/', (string) $order->comment) as $line) {
            if (str_starts_with($line, '📍')) {
                return trim(mb_substr($line, 1)) ?: null;
            }
        }

        return null;
    }

    protected function renderStatusPill(Order $order): string
    {
        return $this->makePartial('orderstatuspill', ['order' => $order]);
    }

    protected function renderOrderModal(Order $order): string
    {
        $canceled = $this->canceledStatusId();
        $next = $this->nextStatusId($order);

        $address = self::deliveryAddress($order);
        $metadata = VoxPilotOrderMetadata::where('order_id', $order->order_id)->first();

        return $this->makePartial('ordermodal', [
            'order' => $order,
            'history' => $this->customerHistory($order),
            'address' => $address,
            'mapUrl' => $address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null,
            'staff' => $order->order_type === 'delivery' ? $this->assignableStaff($order) : collect(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'etaSteps' => self::ETA_STEPS,
            'readyAt' => VoxPilotStatusNotifier::readyAt($order),
            'phone' => (bool) $metadata,
            // The call that placed it (pos-gateway SPEC-006), so the staff can check what was said.
            'transcript' => self::transcriptTurns($metadata?->transcript),
            'statuses' => $this->quickStatuses()->reject(fn ($s) => (int) $s->status_id === $canceled)->values(),
            'nextStatus' => $next ? $this->quickStatuses()->firstWhere('status_id', $next) : null,
            'canceledId' => $canceled,
            'isCanceled' => $canceled && (int) $order->status_id === $canceled,
        ]);
    }
}
