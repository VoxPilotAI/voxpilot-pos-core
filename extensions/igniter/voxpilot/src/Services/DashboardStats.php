<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Support\StatusLabel;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the VoxPilot POS dashboard widgets, from this POS's own orders. Order queries go
 * through the Order model, so TenantOrderScope keeps an owner to their restaurant; canceled and
 * unfinished (status 0) orders are left out. Phone orders are the ones VoxPilot created
 * (a voxpilot_order_metadata row).
 */
class DashboardStats
{
    /** Orders loaded per range; a demo/small-restaurant POS stays far below it. */
    private const MAX_ORDERS = 5000;

    protected CarbonImmutable $start;

    protected CarbonImmutable $end;

    protected ?Collection $orders = null;

    protected ?array $phoneOrderIds = null;

    public function __construct(?DateTimeInterface $start, ?DateTimeInterface $end)
    {
        $this->end = CarbonImmutable::instance($end ?? now())->endOfDay();
        $this->start = CarbonImmutable::instance($start ?? $this->end->subDays(29))->startOfDay();
    }

    public static function forRange(?DateTimeInterface $start, ?DateTimeInterface $end): self
    {
        static $cache = [];
        $key = ($start?->format('Y-m-d') ?? '-').'|'.($end?->format('Y-m-d') ?? '-');

        return $cache[$key] ??= new self($start, $end);
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    /** @return array{revenue: float, orders: int, average: float, phoneOrders: int, phoneShare: int, revenueDelta: ?int, ordersDelta: ?int, averageDelta: ?int, revenueSpark: int[], ordersSpark: int[], averageSpark: int[], phoneSpark: int[]} */
    public function kpis(): array
    {
        $orders = $this->orders();
        $revenue = (float) $orders->sum('order_total');
        $count = $orders->count();
        $average = $count ? $revenue / $count : 0.0;
        $phone = $orders->filter(fn ($o) => $this->isPhone($o))->count();

        $days = max(1, (int) $this->start->diffInDays($this->end) + 1);
        $previous = $this->baseQuery($this->start->subDays($days), $this->start->subSecond())
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(order_total), 0) as total')
            ->first();
        $prevCount = (int) ($previous->n ?? 0);
        $prevRevenue = (float) ($previous->total ?? 0);
        $prevAverage = $prevCount ? $prevRevenue / $prevCount : 0.0;

        $buckets = $this->dailyBuckets();

        return [
            'revenue' => $revenue,
            'orders' => $count,
            'average' => $average,
            'phoneOrders' => $phone,
            'phoneShare' => $count ? (int) round($phone / $count * 100) : 0,
            'revenueDelta' => $this->delta($revenue, $prevRevenue),
            'ordersDelta' => $this->delta($count, $prevCount),
            'averageDelta' => $this->delta($average, $prevAverage),
            'revenueSpark' => $this->normalize(array_column($buckets, 'revenue')),
            'ordersSpark' => $this->normalize(array_column($buckets, 'orders')),
            'averageSpark' => $this->normalize(array_map(fn ($b) => $b['orders'] ? $b['revenue'] / $b['orders'] : 0, $buckets)),
            'phoneSpark' => $this->normalize(array_column($buckets, 'phone')),
        ];
    }

    /** @return array<int, array{hour: int, phone: int, other: int}> hours that had orders, padded to a continuous span */
    public function byHour(): array
    {
        $hours = [];
        foreach ($this->orders() as $order) {
            $hour = (int) substr((string) $order->order_time, 0, 2);
            $hours[$hour] ??= ['hour' => $hour, 'phone' => 0, 'other' => 0];
            $hours[$hour][$this->isPhone($order) ? 'phone' : 'other']++;
        }
        if (!$hours) {
            return [];
        }

        $first = min(array_keys($hours));
        $last = max(array_keys($hours));
        $span = [];
        for ($h = $first; $h <= $last; $h++) {
            $span[] = $hours[$h] ?? ['hour' => $h, 'phone' => 0, 'other' => 0];
        }

        return $span;
    }

    /** @return array<int, array{id: int, customer: string, time: string, items: string, phone: bool, status: string, color: string, total: float, type: string}> */
    public function recent(int $limit = 6): array
    {
        $orders = Order::query()
            ->with(['status', 'menus'])
            ->where('status_id', '>', 0)
            ->orderByDesc('order_id')
            ->limit($limit)
            ->get();

        $phoneIds = VoxPilotOrderMetadata::whereIn('order_id', $orders->pluck('order_id'))->pluck('order_id')->all();

        return $orders->map(fn ($order) => [
            'id' => (int) $order->order_id,
            'customer' => trim($order->first_name.' '.$order->last_name) ?: '—',
            'time' => $order->created_at?->format('d M H:i') ?? '',
            'items' => $order->menus->map(fn ($m) => $m->quantity.'× '.$m->name)->implode(', '),
            'phone' => in_array($order->order_id, $phoneIds),
            'status' => $order->status ? StatusLabel::for($order->status->status_name) : '—',
            'color' => (string) ($order->status?->status_color ?? '#8a96b4'),
            'total' => (float) $order->order_total,
            'type' => (string) $order->order_type,
        ])->all();
    }

    /** @return array<int, array{name: string, quantity: int, share: int}> */
    public function topItems(int $limit = 5): array
    {
        $ids = $this->orders()->pluck('order_id')->all();
        if (!$ids) {
            return [];
        }

        $rows = DB::table('order_menus')
            ->whereIn('order_id', $ids)
            ->selectRaw('name, SUM(quantity) as qty')
            ->groupBy('name')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();

        $max = max(1, (int) ($rows->first()->qty ?? 1));

        return $rows->map(fn ($r) => [
            'name' => (string) $r->name,
            'quantity' => (int) $r->qty,
            'share' => (int) round($r->qty / $max * 100),
        ])->all();
    }

    /** Orders confirmed by the VoxPilot assistant in the range, from this POS's own records. */
    public function phoneOrders(): int
    {
        return $this->orders()->filter(fn ($o) => $this->isPhone($o))->count();
    }

    /**
     * Close of the day for the range's last day: totals by payment (unpaid apart), by order type,
     * phone orders and the canceled ones (left out of every other figure).
     *
     * @return array{day: string, orders: int, revenue: float, phoneOrders: int,
     *     payments: array<int, array{label: string, orders: int, total: float, unpaid: bool}>,
     *     types: array<string, array{orders: int, total: float}>, canceled: array{orders: int, total: float}}
     */
    public function daySummary(): array
    {
        $day = $this->end->toDateString();
        $canceledId = (int) setting('canceled_order_status');
        $all = Order::query()
            ->with(['payment_method', 'payment_logs' => fn ($q) => $q->where('is_success', true)])
            ->where('status_id', '>', 0)
            ->whereDate('order_date', $day)
            ->limit(self::MAX_ORDERS)
            ->get(['order_id', 'order_total', 'status_id', 'order_type', 'payment', 'processed']);

        [$canceled, $orders] = $all->partition(fn ($o) => $canceledId && (int) $o->status_id === $canceledId);
        $phoneIds = array_flip(VoxPilotOrderMetadata::whereIn('order_id', $orders->pluck('order_id'))->pluck('order_id')->all());

        $payments = $orders
            // Paid by the method TastyIgniter knows, else the one the staff chose when marking it paid.
            ->groupBy(fn ($o) => $o->processed
                ? ($o->payment_method?->name ?: ($o->payment_logs->last()?->payment_name ?: lang('igniter.voxpilot::orders.paid')))
                : '')
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'orders' => $group->count(),
                'total' => round((float) $group->sum('order_total'), 2),
                'unpaid' => $label === '',
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $types = [];
        foreach (['delivery', 'collection'] as $type) {
            $group = $orders->filter(fn ($o) => ($o->order_type === 'delivery' ? 'delivery' : 'collection') === $type);
            $types[$type] = ['orders' => $group->count(), 'total' => round((float) $group->sum('order_total'), 2)];
        }

        return [
            'day' => $day,
            'orders' => $orders->count(),
            'revenue' => round((float) $orders->sum('order_total'), 2),
            'phoneOrders' => $orders->filter(fn ($o) => isset($phoneIds[$o->order_id]))->count(),
            'payments' => $payments,
            'types' => $types,
            'canceled' => ['orders' => $canceled->count(), 'total' => round((float) $canceled->sum('order_total'), 2)],
        ];
    }

    protected function orders(): Collection
    {
        return $this->orders ??= $this->baseQuery($this->start, $this->end)
            ->select(['order_id', 'order_total', 'order_date', 'order_time', 'status_id', 'order_type'])
            ->orderBy('order_id')
            ->limit(self::MAX_ORDERS)
            ->get();
    }

    protected function baseQuery(CarbonImmutable $from, CarbonImmutable $to)
    {
        $query = Order::query()
            ->where('status_id', '>', 0)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

        if ($canceled = (int) setting('canceled_order_status')) {
            $query->where('status_id', '!=', $canceled);
        }

        return $query;
    }

    protected function isPhone($order): bool
    {
        $this->phoneOrderIds ??= array_flip(
            VoxPilotOrderMetadata::whereIn('order_id', $this->orders()->pluck('order_id'))->pluck('order_id')->all()
        );

        return isset($this->phoneOrderIds[$order->order_id]);
    }

    /** One bucket per day for the last (up to) 14 days of the range. */
    protected function dailyBuckets(): array
    {
        $days = min(14, (int) $this->start->diffInDays($this->end) + 1);
        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $buckets[$this->end->subDays($i)->toDateString()] = ['revenue' => 0.0, 'orders' => 0, 'phone' => 0];
        }
        foreach ($this->orders() as $order) {
            $day = substr((string) $order->order_date, 0, 10);
            if (!isset($buckets[$day])) {
                continue;
            }
            $buckets[$day]['revenue'] += (float) $order->order_total;
            $buckets[$day]['orders']++;
            $buckets[$day]['phone'] += $this->isPhone($order) ? 1 : 0;
        }

        return array_values($buckets);
    }

    /** @return int[] values scaled to 8–100 (bar heights in %) */
    protected function normalize(array $values): array
    {
        $max = max($values ?: [0]);

        return array_map(fn ($v) => $max > 0 ? max(8, (int) round($v / $max * 100)) : 8, $values);
    }

    protected function delta(float|int $now, float|int $before): ?int
    {
        if ($before <= 0) {
            return null;
        }

        return (int) round(($now - $before) / $before * 100);
    }
}
