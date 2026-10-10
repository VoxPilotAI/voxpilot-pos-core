<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Models\TenantStaffRole;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class ResolveTenantForAdmin
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::guard('igniter-admin')->user();
        if (!$user) {
            return $next($request);
        }

        $membership = TenantMembership::with('tenant')
            ->where('user_id', $user->user_id)
            ->orderBy('id')
            ->first();

        if ($membership && $membership->tenant) {
            $this->tenantContext->set($membership->tenant);
            // The admin's tab title and logo alt read the restaurant's name, not the shared site name.
            View::share('site_name', $membership->tenant->name);
        } elseif (!$user->super_user) {
            // Multi-tenant POS: staff who belong to no restaurant see no orders or locations.
            $this->tenantContext->denyAll();
        }

        if (!$user->super_user) {
            // Roles are shared by every restaurant: for this request the staff member's role loses the
            // platform permissions (statuses, settings, languages, payment gateways…), which hides those
            // pages and blocks them. Staff of no restaurant keep no permission at all.
            $user->setRelation('role', TenantStaffRole::limitedCopyOf($user->role, noPermissions: !$this->tenantContext->isActive()));
        }

        return $next($request);
    }
}
