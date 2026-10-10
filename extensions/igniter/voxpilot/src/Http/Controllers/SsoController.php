<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\SsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * GET /voxpilot/sso?token=… — "Open POS" from VoxPilot signs the owner in (pos-gateway SPEC-006).
 * The token never stays in the browser: the answer is a redirect, with no referrer.
 */
class SsoController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $tenantId = SsoToken::verify((string) $request->query('token'));
        $tenant = $tenantId ? Tenant::where('external_tenant_id', $tenantId)->where('status', 'active')->first() : null;
        $owner = $tenant
            ? User::query()
                ->whereIn('user_id', TenantMembership::where('tenant_id', $tenant->id)->where('role', 'owner')->pluck('user_id'))
                ->where('status', true)
                ->first()
            : null;

        if (!$owner) {
            Log::warning('[voxpilot] sso refused', ['tenant' => $tenantId, 'reason' => $tenantId ? 'no active owner' : 'invalid token']);

            return $this->noReferrer(redirect()->to(admin_url('login')));
        }

        AdminAuth::login($owner, false);
        $request->session()->regenerate();

        return $this->noReferrer(redirect()->to(admin_url('dashboard')));
    }

    protected function noReferrer(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
