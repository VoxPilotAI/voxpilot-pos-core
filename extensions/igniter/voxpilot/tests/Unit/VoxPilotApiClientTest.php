<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Services\TenantContext;
use Igniter\VoxPilot\Services\VoxPilotApiClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The POS dashboard reads its assistant figures from VoxPilot with a request signed for its own
 * tenant and connection; without a connected install it asks nothing.
 */
class VoxPilotApiClientTest extends TestCase
{
    use DatabaseTransactions;

    protected string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('s', 64);
        config(['voxpilot.hmac_shared_secret' => $this->secret, 'voxpilot.api_url' => null]);
        Cache::flush();
    }

    protected function tenant(string $status = Installation::STATUS_CONNECTED): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Stats Pizzeria',
            'slug' => 'stats-pizzeria-'.uniqid(),
            'external_tenant_id' => 'vp_tenant_'.uniqid(),
            'status' => 'active',
            'settings' => ['webhook_callback_url' => 'https://api.voxpilot.example'],
        ]);
        Installation::create([
            'tenant_id' => $tenant->id,
            'external_tenant_id' => $tenant->external_tenant_id,
            'status' => $status,
            'voxpilot_connection_id' => 'conn_test_123',
            'assistant_id' => 'agent_1',
        ]);
        app(TenantContext::class)->set($tenant);

        return $tenant;
    }

    public function test_signs_the_request_for_its_own_tenant_and_connection(): void
    {
        $tenant = $this->tenant();
        Http::fake(['api.voxpilot.example/*' => Http::response(['success' => true, 'data' => ['orders' => ['confirmed' => 3]]])]);

        $stats = app(VoxPilotApiClient::class)->assistantStats(now()->subDay(), now());

        $this->assertSame(3, $stats['orders']['confirmed']);
        Http::assertSent(function (Request $request) use ($tenant) {
            $path = substr($request->url(), strlen('https://api.voxpilot.example'));
            $signed = implode("\n", [$request->header('X-VoxPilot-Timestamp')[0], 'GET', $path, $tenant->external_tenant_id, 'conn_test_123']);

            return str_starts_with($path, '/pos/assistant-stats?from=')
                && $request->header('X-VoxPilot-Tenant')[0] === $tenant->external_tenant_id
                && $request->header('X-VoxPilot-Connection')[0] === 'conn_test_123'
                && $request->header('X-VoxPilot-Signature')[0] === 'v1='.hash_hmac('sha256', $signed, $this->secret);
        });
    }

    public function test_asks_nothing_without_a_connected_install(): void
    {
        $this->tenant(Installation::STATUS_DISCONNECTED);
        Http::fake();

        $this->assertNull(app(VoxPilotApiClient::class)->assistantStats(now()->subDay(), now()));
        Http::assertNothingSent();
    }

    public function test_returns_null_when_voxpilot_refuses(): void
    {
        $this->tenant();
        Http::fake(['*' => Http::response(['code' => 'forbidden'], 403)]);

        $this->assertNull(app(VoxPilotApiClient::class)->assistantStats(now()->subDay(), now()));
    }
}
