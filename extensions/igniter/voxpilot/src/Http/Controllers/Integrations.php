<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Models\TenantMembership;
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
        $tenantId = $this->getAdminTenantId();

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

        return $this->makeView('igniter.voxpilot::integrations.index');
    }

    public function onCreate(Request $request): RedirectResponse
    {
        $tenantId = $this->getAdminTenantId();
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
            defaultLocationId: $defaultLocationId ? (int)$defaultLocationId : null,
            createdByUserId: $this->getUser()->user_id,
        );

        flash()->success('API token created. Copy it now — it will not be shown again.');

        return redirect()->to(admin_url('igniter/voxpilot/integrations'))
            ->with('voxpilot_new_token', $result['plain_text']);
    }

    public function onRevoke(Request $request): RedirectResponse
    {
        $tenantId = $this->getAdminTenantId();
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

    protected function getAdminTenantId(): ?int
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        $membership = TenantMembership::where('user_id', $user->user_id)->first();
        return $membership?->tenant_id;
    }
}
