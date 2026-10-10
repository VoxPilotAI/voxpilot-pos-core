<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        } elseif (!$user->super_user) {
            // Multi-tenant POS: staff who belong to no restaurant see no orders or locations.
            $this->tenantContext->denyAll();
        }

        return $next($request);
    }
}
