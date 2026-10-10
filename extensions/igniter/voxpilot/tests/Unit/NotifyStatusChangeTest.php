<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Jobs\NotifyStatusChange;
use Igniter\VoxPilot\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Status changes of a VoxPilot order go to VoxPilot's public webhook (or VOXPILOT_API_URL), signed
 * over the exact body sent; VoxPilot's order gateway applies them (pos-gateway SPEC-000).
 */
class NotifyStatusChangeTest extends TestCase
{
    use DatabaseTransactions;

    protected function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Status Pizzeria',
            'slug' => 'status-pizzeria-'.uniqid(),
            'external_tenant_id' => 'vp_status_'.uniqid(),
            'status' => 'active',
            'settings' => ['webhook_callback_url' => 'https://api.voxpilot.example'],
        ]);
    }

    public function test_posts_a_signed_status_change_to_the_public_webhook(): void
    {
        config(['voxpilot.hmac_shared_secret' => str_repeat('h', 64), 'voxpilot.api_url' => null]);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $tenant = $this->tenant();

        (new NotifyStatusChange(61, $tenant->id, 'ord_1', 'Preparación', 'Mañana / tarde'))->handle();

        Http::assertSent(function (Request $request) use ($tenant) {
            $timestamp = $request->header('X-VoxPilot-Timestamp')[0];
            $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), str_repeat('h', 64));

            return $request->url() === 'https://api.voxpilot.example/pos/webhooks/order-status'
                && $request->header('X-VoxPilot-Signature')[0] === $expected
                && $request['external_order_id'] === 'ord_1'
                && $request['tenant_id'] === $tenant->external_tenant_id
                && $request['status'] === 'Preparación';
        });
    }

    public function test_sends_the_kitchen_estimate_when_the_staff_set_one(): void
    {
        config(['voxpilot.hmac_shared_secret' => str_repeat('h', 64), 'voxpilot.api_url' => 'http://backend:3000']);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $order = \Igniter\Cart\Models\Order::withoutGlobalScopes()->first();
        if (!$order) {
            $this->markTestSkipped('No order available');
        }
        $order->order_date = '2026-10-09';
        $order->order_time = '19:45';
        $order->order_time_is_asap = false;
        $order->saveQuietly();

        (new NotifyStatusChange((int) $order->order_id, $this->tenant()->id, 'ord_1', 'Pending', null))->handle();

        Http::assertSent(fn (Request $request) => str_starts_with((string) $request['ready_at'], '2026-10-09T19:45:00'));
    }

    public function test_uses_voxpilot_api_url_when_configured(): void
    {
        config(['voxpilot.hmac_shared_secret' => str_repeat('h', 64), 'voxpilot.api_url' => 'http://backend:3000']);
        Http::fake(['*' => Http::response(['ok' => true])]);

        (new NotifyStatusChange(61, $this->tenant()->id, 'ord_1', 'Completed', null))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://backend:3000/pos/webhooks/order-status');
    }
}
