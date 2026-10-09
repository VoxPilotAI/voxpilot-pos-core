<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\InstallationService;
use Igniter\System\Mail\AnonymousTemplateMailable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Operation A (provision) and Operation B (Connect → install) over HTTP, as VoxPilot Force calls them.
 * Provisioning never mints an order token; the token only exists after an install bound to one assistant.
 */
class ProvisioningEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = str_repeat('p', 40);
        config(['voxpilot.provisioning_secret' => $this->secret, 'voxpilot.send_owner_invite' => true]);
    }

    public function test_rejects_missing_and_invalid_secret(): void
    {
        $this->postJson('/api/voxpilot/provision/tenants', $this->payload())
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'PROVISIONING_AUTH_FAILED');

        $this->postJson('/api/voxpilot/provision/tenants', $this->payload(), $this->auth('wrong-secret-'.str_repeat('x', 40)))
            ->assertStatus(401);
    }

    public function test_returns_503_when_secret_is_not_configured(): void
    {
        config(['voxpilot.provisioning_secret' => null]);

        $this->postJson('/api/voxpilot/provision/tenants', $this->payload(), $this->auth())
            ->assertStatus(503);
    }

    public function test_creates_tenant_owner_location_and_membership_without_a_token(): void
    {
        $payload = $this->payload();
        $response = $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth());

        $response->assertStatus(201)
            ->assertJsonPath('data.provisioned', true)
            ->assertJsonPath('data.external_tenant_id', $payload['external_tenant_id'])
            ->assertJsonPath('data.api_token', null)
            ->assertJsonPath('data.owner_invite_sent', true);
        $this->assertStringEndsWith('/admin', (string) $response->json('data.pos_admin_url'));

        $tenant = Tenant::where('external_tenant_id', $payload['external_tenant_id'])->firstOrFail();
        $owner = User::where('email', $payload['admin_email'])->firstOrFail();

        $this->assertSame((int) $tenant->id, (int) $response->json('data.tenant_id'));
        $this->assertTrue(Location::where('tenant_id', $tenant->id)->where('location_id', $response->json('data.location_id'))->exists());
        $this->assertTrue(TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $owner->user_id)->where('role', 'owner')->exists());
        $this->assertSame(0, TenantApiToken::where('tenant_id', $tenant->id)->count(), 'provisioning must not mint an order token');
        // The owner can connect VoxPilot and see its orders (both tenant-scoped), nothing global.
        $this->assertTrue($owner->fresh()->hasPermission('Igniter.VoxPilot.Manage'));
        $this->assertTrue($owner->fresh()->hasPermission('Admin.Orders'));
        $this->assertFalse($owner->fresh()->hasPermission('Admin.Menus'));
        // ...and is assigned to the restaurant's location (TastyIgniter lists orders per location).
        $this->assertTrue($owner->fresh()->locations()->where('locations.location_id', $response->json('data.location_id'))->exists());
        // The invite is TastyIgniter's set-your-password link: a reset code and the invite time.
        $this->assertNotNull($owner->fresh()->invited_at);
        $this->assertNotEmpty($owner->fresh()->reset_code);
    }

    public function test_is_idempotent_by_external_tenant_id(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth())->assertStatus(201);
        $tenantsAfterFirst = Tenant::count();

        $second = $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth());

        $second->assertStatus(200)
            ->assertJsonPath('data.provisioned', false)
            ->assertJsonPath('data.tenant_id', $first->json('data.tenant_id'))
            ->assertJsonPath('data.location_id', $first->json('data.location_id'))
            ->assertJsonPath('data.api_token', null)
            ->assertJsonPath('data.owner_invite_sent', false);
        $this->assertSame($tenantsAfterFirst, Tenant::count());
    }

    public function test_owner_gets_the_voxpilot_language_for_invite_and_password_emails(): void
    {
        Mail::fake();
        $payload = $this->payload() + ['locale' => 'es-CR'];

        $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth())
            ->assertStatus(201)
            ->assertJsonPath('data.owner_invite_sent', true);

        $owner = User::where('email', $payload['admin_email'])->firstOrFail();
        // Stored as the owner's TastyIgniter language: the admin locale follows it.
        $this->assertSame('es', $owner->getLocale());
        $this->assertTrue((bool) $owner->language->status);

        Mail::assertQueued(AnonymousTemplateMailable::class, fn (AnonymousTemplateMailable $mail) => $mail->getTemplateCode() === 'igniter.user::mail.invite'
            && $mail->locale === 'es'
            && $mail->hasTo($payload['admin_email']));

        // A later "forgot password" from the signed-out admin login uses it too.
        $owner->mailSendResetPasswordRequest(['reset_link' => 'https://pos.test/admin/login/reset?code=x']);
        Mail::assertQueued(AnonymousTemplateMailable::class, fn (AnonymousTemplateMailable $mail) => $mail->getTemplateCode() === 'igniter.user::mail.admin_password_reset_request'
            && $mail->locale === 'es');
    }

    public function test_owner_language_falls_back_to_english_for_unsupported_languages(): void
    {
        Mail::fake();
        $payload = $this->payload() + ['locale' => 'ja'];

        $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth())
            ->assertStatus(201);

        $this->assertSame('en', User::where('email', $payload['admin_email'])->firstOrFail()->getLocale());
        Mail::assertQueued(AnonymousTemplateMailable::class, fn (AnonymousTemplateMailable $mail) => $mail->locale === 'en');
    }

    public function test_owner_invite_can_be_skipped(): void
    {
        $payload = $this->payload() + ['send_owner_invite' => false];

        $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth())
            ->assertStatus(201)
            ->assertJsonPath('data.owner_invite_sent', false);

        $this->assertNull(User::where('email', $payload['admin_email'])->firstOrFail()->invited_at);
    }

    public function test_connect_install_mints_one_token_bound_to_one_assistant_and_it_can_create_an_order(): void
    {
        $payload = $this->payload();
        $provisioned = $this->postJson('/api/voxpilot/provision/tenants', $payload, $this->auth())->assertStatus(201);
        $tenantId = (int) $provisioned->json('data.tenant_id');

        // POS admin clicks Connect with VoxPilot: one-time code + state for the browser redirect.
        $authorization = app(InstallationService::class)->createAuthorization($tenantId);
        $this->assertStringNotContainsString($payload['external_tenant_id'], $authorization['authorization_url']);

        $exchange = $this->postJson('/api/voxpilot/oauth/token', [
            'code' => $authorization['code'],
            'state' => $authorization['state'],
        ], $this->auth());
        $exchange->assertOk()->assertJsonPath('data.external_tenant_id', $payload['external_tenant_id']);
        // VoxPilot sends the owner back here once the install completes.
        $this->assertSame(admin_url('igniter/voxpilot/integrations'), $exchange->json('data.return_url'));

        // The same code cannot be used twice.
        $this->postJson('/api/voxpilot/oauth/token', [
            'code' => $authorization['code'],
            'state' => $authorization['state'],
        ], $this->auth())->assertStatus(400)->assertJsonPath('error.code', 'CODE_ALREADY_USED');

        $activate = $this->postJson('/api/voxpilot/installations/activate', [
            'external_tenant_id' => $payload['external_tenant_id'],
            'voxpilot_connection_id' => 'conn_test_1',
            'assistant_id' => 'asst_test_1',
        ], $this->auth());
        $activate->assertOk()->assertJsonPath('data.status', Installation::STATUS_CONNECTED);
        $token = (string) $activate->json('data.api_token');
        $this->assertNotSame('', $token);

        $installation = Installation::where('tenant_id', $tenantId)->firstOrFail();
        $this->assertSame('asst_test_1', $installation->assistant_id);

        // A second connection for the same POS is refused while the first one is active.
        $this->postJson('/api/voxpilot/installations/activate', [
            'external_tenant_id' => $payload['external_tenant_id'],
            'voxpilot_connection_id' => 'conn_test_2',
            'assistant_id' => 'asst_test_2',
        ], $this->auth())->assertStatus(409);

        $order = $this->postJson('/api/voxpilot/orders', $this->orderPayload(), ['Authorization' => 'Bearer '.$token]);
        $order->assertStatus(201);
        $this->assertSame((int) $provisioned->json('data.location_id'), (int) $order->json('data.location.id'));

        // Disconnect revokes the token; the POS tenant stays.
        $this->postJson('/api/voxpilot/installations/deactivate', [
            'external_tenant_id' => $payload['external_tenant_id'],
        ], $this->auth())->assertOk();
        $this->postJson('/api/voxpilot/orders', $this->orderPayload('vp_prov_after_disconnect'), ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
        $this->assertTrue(Tenant::whereKey($tenantId)->exists());
    }

    protected function auth(?string $secret = null): array
    {
        return ['Authorization' => 'Bearer '.($secret ?? $this->secret)];
    }

    protected function payload(): array
    {
        $id = uniqid('prov', true);

        return [
            'external_tenant_id' => 'force-user-'.$id,
            'company_name' => 'Provision Cafe '.$id,
            'admin_email' => 'owner-'.str_replace('.', '', $id).'@example.test',
        ];
    }

    protected function orderPayload(string $externalOrderId = 'vp_prov_order_001'): array
    {
        return [
            'external_order_id' => $externalOrderId,
            'source' => 'voice',
            'customer' => ['name' => 'Provision Test', 'phone' => '+50688887777'],
            'fulfillment' => ['type' => 'pickup', 'requested_time' => 'ASAP'],
            'items' => [['name' => 'Soda', 'quantity' => 1, 'unit_price' => 2.50]],
        ];
    }
}
