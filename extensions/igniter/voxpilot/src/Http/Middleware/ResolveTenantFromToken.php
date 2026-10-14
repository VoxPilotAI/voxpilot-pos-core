<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Services\LanguagePreference;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResolveTenantFromToken
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return $this->unauthorized('Missing authentication token.');
        }

        $token = TenantApiToken::findByBearerToken($bearer);
        if (!$token) {
            return $this->unauthorized('Invalid authentication token.');
        }

        if ($token->isRevoked()) {
            return $this->unauthorized('Token has been revoked.');
        }

        $tenant = $token->tenant;
        if (!$tenant || $tenant->status !== 'active') {
            return $this->unauthorized('Tenant is inactive or not found.');
        }

        $token->markAsUsed();

        $this->tenantContext->set($tenant, $token->default_location_id);
        // Notes written on orders (changed by phone, not available now…) in the restaurant team's language.
        if ($locale = (new LanguagePreference())->tenantLocale($tenant)) {
            app()->setLocale($locale);
        }

        $request->attributes->set('voxpilot_tenant', $tenant);
        $request->attributes->set('voxpilot_token', $token);

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'AUTHENTICATION_FAILED',
                'message' => $message,
            ],
        ], 401);
    }
}
