<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\InstallationException;
use Igniter\VoxPilot\Services\InstallationService;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Integrations extends AdminController
{
    protected null|string|array $requiredPermissions = 'Igniter.VoxPilot.Manage';

    public function __construct()
    {
        parent::__construct();

        AdminMenu::setContext('voxpilot', 'tools');
    }

    public function index(): mixed
    {
        $tenantId = $this->resolveTenantId();

        $this->vars['tokens'] = $tenantId
            ? TenantApiToken::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        $this->vars['locations'] = $tenantId
            ? Location::where('tenant_id', $tenantId)->get()
            : collect();

        $this->vars['newToken'] = session('voxpilot_new_token');
        $this->vars['tenantId'] = $tenantId;
        $this->vars['installation'] = $tenantId
            ? Installation::where('tenant_id', $tenantId)->first()
            : null;

        return $this->makeView('igniter.voxpilot::integrations.index');
    }

    /** SPEC-011: Connect with VoxPilot — redirect browser with opaque code. */
    public function onConnect(): RedirectResponse
    {
        $tenantId = $this->resolveTenantId();
        if (!$tenantId) {
            flash()->error('No tenant found. Run: php artisan voxpilot:bootstrap-tenant');
            return back();
        }

        try {
            $result = app(InstallationService::class)->createAuthorization(
                $tenantId,
                $this->getUser()?->user_id,
            );

            return redirect()->away($result['authorization_url']);
        } catch (InstallationException $e) {
            flash()->error($e->getMessage());
            return back();
        }
    }

    /** SPEC-011: Disconnect VoxPilot — revoke install tokens, keep POS tenant. */
    public function onDisconnect(): RedirectResponse
    {
        $tenantId = $this->resolveTenantId();
        if (!$tenantId) {
            flash()->error('No tenant found.');
            return back();
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant?->external_tenant_id) {
            flash()->error('Tenant is missing external_tenant_id; cannot disconnect.');
            return back();
        }

        try {
            app(InstallationService::class)->deactivate((string) $tenant->external_tenant_id);
            flash()->success('VoxPilot disconnected. Your POS tenant was kept.');
        } catch (InstallationException $e) {
            flash()->error($e->getMessage());
        }

        return redirect()->to(admin_url('igniter/voxpilot/integrations'));
    }

    public function onCreate(Request $request): RedirectResponse
    {
        $tenantId = $this->resolveTenantId();
        if (!$tenantId) {
            flash()->error('No tenant found. Run: php artisan voxpilot:bootstrap-tenant');
            return back();
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'default_location_id' => 'nullable|integer',
        ]);

        $defaultLocationId = $request->input('default_location_id') ?: null;

        if ($defaultLocationId) {
            $locationBelongs = Location::where('location_id', $defaultLocationId)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (!$locationBelongs) {
                flash()->error('Selected location does not belong to your tenant.');
                return back();
            }
        }

        $result = TenantApiToken::generateToken(
            tenantId: $tenantId,
            name: $request->input('name'),
            defaultLocationId: $defaultLocationId ? (int) $defaultLocationId : null,
            createdByUserId: $this->getUser()->user_id,
        );

        flash()->success('API token created. Copy it now — it will not be shown again.');

        return redirect()->to(admin_url('igniter/voxpilot/integrations'))
            ->with('voxpilot_new_token', $result['plain_text']);
    }

    public function onRevoke(Request $request): RedirectResponse
    {
        $tenantId = $this->resolveTenantId();
        $tokenId = $request->input('token_id');

        $token = TenantApiToken::where('id', $tokenId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($token && !$token->isRevoked()) {
            $token->revoke();
            flash()->success("Token \"{$token->name}\" has been revoked.");
        }

        return redirect()->to(admin_url('igniter/voxpilot/integrations'));
    }

    protected function resolveTenantId(): ?int
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        $context = app(TenantContext::class);
        $tenant = $context->resolveForAdmin($user);

        return $tenant?->id;
    }
}
