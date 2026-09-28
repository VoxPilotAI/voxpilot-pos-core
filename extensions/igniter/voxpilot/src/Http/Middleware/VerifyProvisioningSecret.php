<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyProvisioningSecret
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configured = config('voxpilot.provisioning_secret');

        if (!$configured || strlen($configured) < 32) {
            return $this->error('Provisioning endpoint is not configured.', 503);
        }

        $bearer = $request->bearerToken();
        if (!$bearer) {
            return $this->error('Missing provisioning token.', 401);
        }

        if (!hash_equals($configured, $bearer)) {
            return $this->error('Invalid provisioning token.', 401);
        }

        return $next($request);
    }

    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PROVISIONING_AUTH_FAILED',
                'message' => $message,
            ],
        ], $status);
    }
}
