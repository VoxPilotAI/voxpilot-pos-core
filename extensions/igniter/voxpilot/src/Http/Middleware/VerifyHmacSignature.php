<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Verifies X-VoxPilot-Signature HMAC on incoming webhook deliveries.
 * This is an ALTERNATIVE to Bearer token auth — the endpoint accepts either.
 */
class VerifyHmacSignature
{
    protected const MAX_TIMESTAMP_DRIFT_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header('X-VoxPilot-Signature');
        $timestamp = $request->header('X-VoxPilot-Timestamp');

        if (!$signature || !$timestamp) {
            return $next($request);
        }

        $secret = config('voxpilot.hmac_shared_secret');
        if (!$secret || strlen($secret) < 32) {
            return response()->json([
                'error' => [
                    'code' => 'HMAC_NOT_CONFIGURED',
                    'message' => 'HMAC verification is not configured on this server.',
                ],
            ], 503);
        }

        $drift = abs(time() - (int) $timestamp);
        if ($drift > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
            return response()->json([
                'error' => [
                    'code' => 'TIMESTAMP_STALE',
                    'message' => 'Request timestamp is too old or too far in the future.',
                ],
            ], 401);
        }

        $rawBody = $request->getContent();
        $expected = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_SIGNATURE',
                    'message' => 'HMAC signature verification failed.',
                ],
            ], 401);
        }

        $request->attributes->set('voxpilot_hmac_verified', true);

        return $next($request);
    }
}
