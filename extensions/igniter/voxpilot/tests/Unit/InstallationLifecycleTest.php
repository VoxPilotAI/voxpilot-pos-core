<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Services\InstallationException;
use Igniter\VoxPilot\Services\InstallationService;
use Igniter\VoxPilot\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** SPEC-011 — provision ≠ install; auth code single-use; activate/deactivate. */
class InstallationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_provision_response_has_null_api_token(): void
    {
        $svc = app(TenantProvisioningService::class);
        $result = $svc->provision([
            'external_tenant_id' => 'force-user-spec011-'.uniqid(),
            'company_name' => 'Spec011 Cafe',
            'admin_email' => 'spec011-'.uniqid().'@example.test',
        ]);

        $this->assertTrue($result['provisioned']);
        $this->assertNull($result['api_token']);
        $this->assertNotEmpty($result['tenant_id']);
    }

    public function test_auth_code_is_single_use(): void
    {
        $provision = app(TenantProvisioningService::class)->provision([
            'external_tenant_id' => 'force-user-code-'.uniqid(),
            'company_name' => 'Code Cafe',
            'admin_email' => 'code-'.uniqid().'@example.test',
        ]);

        $install = app(InstallationService::class);
        $auth = $install->createAuthorization((int) $provision['tenant_id']);

        $first = $install->exchangeCode($auth['code'], $auth['state']);
        $this->assertSame((int) $provision['tenant_id'], $first['pos_tenant_id']);

        try {
            $install->exchangeCode($auth['code'], $auth['state']);
            $this->fail('Expected InstallationException on reused code');
        } catch (InstallationException $e) {
            $this->assertSame('code_already_used', $e->errorCode);
        }
    }

    public function test_activate_and_deactivate(): void
    {
        $external = 'force-user-act-'.uniqid();
        $provision = app(TenantProvisioningService::class)->provision([
            'external_tenant_id' => $external,
            'company_name' => 'Act Cafe',
            'admin_email' => 'act-'.uniqid().'@example.test',
        ]);

        $install = app(InstallationService::class);
        $activated = $install->activate($external, 'conn_test_1', 'asst_1');
        $this->assertSame(Installation::STATUS_CONNECTED, $activated['status']);
        $this->assertNotEmpty($activated['api_token']);

        $row = Installation::where('tenant_id', $provision['tenant_id'])->first();
        $this->assertTrue($row?->isConnected());

        $deactivated = $install->deactivate($external);
        $this->assertSame(Installation::STATUS_DISCONNECTED, $deactivated['status']);
        $row->refresh();
        $this->assertSame(Installation::STATUS_DISCONNECTED, $row->status);
    }
}
