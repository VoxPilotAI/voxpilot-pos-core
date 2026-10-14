<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The API token must carry one of the route's abilities (`ability:orders:create,menu:read`):
 * a menu-only token cannot create, change or cancel orders. Runs after ResolveTenantFromToken.
 */
class RequireTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): mixed
    {
        $token = $request->attributes->get('voxpilot_token');
        if (!$token || !$token->canAny(...$abilities)) {
            return response()->json([
                'error' => ['code' => 'ABILITY_MISSING', 'message' => 'This token cannot do that: it needs '.implode(' or ', $abilities).'.'],
            ], 403);
        }

        return $next($request);
    }
}
