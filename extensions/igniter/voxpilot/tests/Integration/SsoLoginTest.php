<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\SsoToken;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** "Open POS" from VoxPilot signs the owner in once, with a short signed token (SPEC-006). */
class SsoLoginTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected const SECRET = 'ssosecretssosecretssosecretssosecret00';

    protected function token(string $tenantId, int $expiresIn = 60, string $secret = self::SECRET, string $nonce = ''): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            't' => $tenantId, 'e' => time() + $expiresIn, 'n' => $nonce ?: bin2hex(random_bytes(12)), 'a' => 'pos-sso',
        ])), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', 'pos-sso.v1.'.$payload, $secret);
    }

    protected function owner(): array
    {
        config(['voxpilot.hmac_shared_secret' => self::SECRET]);
        $tenant = $this->makeTenant();
        $user = User::create(['name' => 'Owner', 'email' => uniqid().'@sso.test', 'username' => uniqid('u'), 'status' => true]);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->user_id, 'role' => 'owner']);

        return [$tenant, $user];
    }

    public function test_signs_the_owner_in_and_redirects_without_referrer(): void
    {
        [$tenant, $user] = $this->owner();

        $response = $this->get('/voxpilot/sso?token='.$this->token($tenant->external_tenant_id));

        $response->assertRedirect(admin_url('dashboard'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame((int) $user->user_id, (int) AdminAuth::user()?->user_id);
    }

    public function test_a_token_works_once(): void
    {
        [$tenant] = $this->owner();
        $token = $this->token($tenant->external_tenant_id);

        $this->assertSame($tenant->external_tenant_id, SsoToken::verify($token));
        $this->assertNull(SsoToken::verify($token));
    }

    public function test_rejects_tampered_expired_long_lived_and_foreign_tokens(): void
    {
        [$tenant] = $this->owner();
        $good = $this->token($tenant->external_tenant_id);

        $this->assertNull(SsoToken::verify(substr($good, 0, -1).(str_ends_with($good, 'a') ? 'b' : 'a')));
        $this->assertNull(SsoToken::verify($this->token($tenant->external_tenant_id, -5)));
        $this->assertNull(SsoToken::verify($this->token($tenant->external_tenant_id, 3600)));
        $this->assertNull(SsoToken::verify($this->token($tenant->external_tenant_id, 60, 'another-secret-another-secret-another')));
        $this->assertNull(SsoToken::verify(null));

        $this->get('/voxpilot/sso?token=nope')->assertRedirect(admin_url('login'));
        $this->assertFalse(AdminAuth::check());
    }
}
