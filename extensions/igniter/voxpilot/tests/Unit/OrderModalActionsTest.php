<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Cart\Models\Order;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\DashboardStats;
use Igniter\VoxPilot\Services\ManualOrderService;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** The order modal's tools: customer history, mark as paid, assign a driver; and the close of the day. */
class OrderModalActionsTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected function quickActions(): object
    {
        return new class {
            use OrderQuickActions;

            public array $rendered = [];

            public function getUser(): ?User
            {
                return null;
            }

            protected function renderOrderModal(Order $order): string
            {
                $this->rendered[] = [
                    'order' => $order,
                    'history' => $this->customerHistory($order),
                    'staff' => $this->assignableStaff($order),
                ];

                return 'modal';
            }

            protected function afterQuickStatusChange(Order $order): array
            {
                return [];
            }

            public function last(): array
            {
                return end($this->rendered);
            }
        };
    }

    protected function order(array $input = []): Order
    {
        $location = $this->makeLocation($this->tenant ??= $this->makeTenant());
        $menu = $this->makeMenu($location, 'Margherita', 10);

        return (new ManualOrderService())->create($location, $input + [
            'customer_name' => 'Ana', 'telephone' => '+50670001111', 'type' => 'pickup',
            'items' => [['menu_id' => $menu->getKey(), 'quantity' => 1]],
        ]);
    }

    protected ?\Igniter\VoxPilot\Models\Tenant $tenant = null;

    protected function staff(\Igniter\VoxPilot\Models\Tenant $tenant, string $name): User
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@ops.test', 'username' => uniqid('u'), 'status' => true]);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->user_id, 'role' => 'staff']);

        return $user;
    }

    public function test_mark_as_paid_records_the_payment_once(): void
    {
        $order = $this->order();
        $actions = $this->quickActions();

        request()->merge(['order_id' => $order->order_id, 'method' => 'transfer']);
        $actions->onMarkOrderPaid();
        $actions->onMarkOrderPaid();

        $this->assertTrue((bool) $order->fresh()->processed);
        $this->assertSame(1, $order->payment_logs()->count());
        $this->assertSame('vp_transfer', $order->payment_logs()->first()->payment_code);
    }

    public function test_unknown_payment_method_changes_nothing(): void
    {
        $order = $this->order();

        request()->merge(['order_id' => $order->order_id, 'method' => 'bitcoin']);
        $this->quickActions()->onMarkOrderPaid();

        $this->assertFalse((bool) $order->fresh()->processed);
    }

    public function test_assigns_only_the_restaurants_own_staff(): void
    {
        $order = $this->order(['type' => 'delivery', 'address' => 'Calle 5, San José']);
        $driver = $this->staff($this->tenant, 'Driver Mine');
        $stranger = $this->staff($this->makeTenant('Other'), 'Driver Other');
        $actions = $this->quickActions();

        request()->merge(['order_id' => $order->order_id, 'assignee_id' => $stranger->user_id]);
        $actions->onAssignOrder();
        $this->assertNull($order->fresh()->assignee_id);
        $this->assertSame(['Driver Mine'], $actions->last()['staff']->pluck('name')->all());

        request()->merge(['assignee_id' => $driver->user_id]);
        $actions->onAssignOrder();
        $this->assertSame((int) $driver->user_id, (int) $order->fresh()->assignee_id);

        request()->merge(['assignee_id' => 0]);
        $actions->onAssignOrder();
        $this->assertNull($order->fresh()->assignee_id);
    }

    public function test_customer_history_counts_earlier_orders_from_the_same_phone(): void
    {
        $this->order();
        $this->order();
        $current = $this->order();
        $this->order(['telephone' => '+50679999999']);
        $actions = $this->quickActions();

        request()->merge(['order_id' => $current->order_id]);
        $actions->onOpenOrder();
        $history = $actions->last()['history'];

        $this->assertSame(2, $history['count']);
        $this->assertEqualsWithDelta(20.0, $history['total'], 0.001);
        $this->assertCount(2, $history['recent']);
    }

    public function test_kitchen_estimate_sets_the_order_time_and_tells_voxpilot(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-09 19:20:00'));
        $order = $this->order();
        \Igniter\VoxPilot\Models\VoxPilotOrderMetadata::create([
            'order_id' => $order->order_id, 'tenant_id' => $this->tenant->id, 'location_id' => $order->location_id,
            'external_order_id' => 'ord_eta', 'source' => 'voice', 'idempotency_key' => 'ord_eta',
        ]);

        request()->merge(['order_id' => $order->order_id, 'minutes' => 20]);
        $this->quickActions()->onSetOrderEta();
        request()->merge(['minutes' => 17]);
        $this->quickActions()->onSetOrderEta();

        $order->refresh();
        $this->assertFalse((bool) $order->order_time_is_asap);
        $this->assertSame('19:40', \Igniter\VoxPilot\Services\VoxPilotStatusNotifier::readyAt($order)?->format('H:i'));
        \Illuminate\Support\Facades\Queue::assertPushed(\Igniter\VoxPilot\Jobs\NotifyStatusChange::class, 1);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_delivery_address_comes_from_the_voxpilot_comment_line(): void
    {
        $order = $this->order(['type' => 'delivery', 'address' => 'Barrio Escalante, 200 m norte']);

        $this->assertSame('Barrio Escalante, 200 m norte', OrderQuickActions::deliveryAddress($order));
        $this->assertNull(OrderQuickActions::deliveryAddress($this->order()));
    }

    public function test_close_of_the_day_splits_paid_unpaid_types_and_canceled(): void
    {
        $paid = $this->order();
        request()->merge(['order_id' => $paid->order_id, 'method' => 'cash']);
        $this->quickActions()->onMarkOrderPaid();
        $this->order(['type' => 'delivery', 'address' => 'X']);
        $canceled = $this->order();
        $canceled->updateOrderStatus((int) setting('canceled_order_status'));
        $mine = Order::query()->where('tenant_id', $this->tenant->id)->pluck('order_id');

        app(\Igniter\VoxPilot\Services\TenantContext::class)->set($this->tenant);
        $summary = (new DashboardStats(now(), now()))->daySummary();

        $this->assertSame(2, $summary['orders']);
        $this->assertSame(1, $summary['canceled']['orders']);
        $this->assertSame(1, $summary['types']['delivery']['orders']);
        $this->assertSame(1, $summary['types']['collection']['orders']);
        $this->assertSame([false, true], collect($summary['payments'])->pluck('unpaid')->sort()->values()->all());
        $this->assertSame(lang('igniter.voxpilot::orders.pay_cash'), collect($summary['payments'])->firstWhere('unpaid', false)['label']);
        $this->assertCount(3, $mine);
    }
}
