<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantApiTokenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generate_token_creates_record_and_returns_plain_text(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $result = TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'Test Token',
        );

        $this->assertNotNull($result['token']);
        $this->assertNotNull($result['plain_text']);
        $this->assertStringContainsString('vp_pos_', $result['plain_text']);
        $this->assertStringContainsString('|', $result['plain_text']);
        $this->assertEquals('Test Token', $result['token']->name);
        $this->assertEquals($tenant->id, $result['token']->tenant_id);
    }

    public function test_find_by_bearer_token_resolves_valid_token(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $result = TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'Lookup Token',
        );

        $found = TenantApiToken::findByBearerToken($result['plain_text']);

        $this->assertNotNull($found);
        $this->assertEquals($result['token']->id, $found->id);
    }

    public function test_find_by_bearer_token_returns_null_for_invalid(): void
    {
        $this->assertNull(TenantApiToken::findByBearerToken('999|invalid_token'));
    }

    public function test_find_by_bearer_token_returns_null_for_malformed(): void
    {
        $this->assertNull(TenantApiToken::findByBearerToken('no-pipe-here'));
    }

    public function test_revoke_sets_revoked_at(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $result = TenantApiToken::generateToken(
            tenantId: $tenant->id,
            name: 'Revoke Token',
        );

        $token = $result['token'];
        $this->assertFalse($token->isRevoked());

        $token->revoke();
        $token->refresh();

        $this->assertTrue($token->isRevoked());
    }
}
