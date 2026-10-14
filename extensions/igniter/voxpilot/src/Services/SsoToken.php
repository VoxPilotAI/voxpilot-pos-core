<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Sign in to the POS from VoxPilot without a second login (pos-gateway SPEC-006). VoxPilot signs
 * `base64url(json{t: VoxPilot tenant id, e: expiry, n: nonce, a: "pos-sso"}) "." hex(hmac_sha256(
 * "pos-sso.v1." + payload, hmac_shared_secret))`. A token is valid for at most 2 minutes and once.
 */
class SsoToken
{
    public const MAX_TTL_SECONDS = 120;

    /** The VoxPilot tenant id the token is for, or null when it is invalid, expired or used. */
    public static function verify(?string $token, ?int $now = null): ?string
    {
        $secret = (string) config('voxpilot.hmac_shared_secret');
        if (strlen($secret) < 32 || !is_string($token) || strlen($token) > 2048 || !str_contains($token, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', 'pos-sso.v1.'.$payload, $secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        $now ??= time();
        if (!is_array($data) || ($data['a'] ?? null) !== 'pos-sso'
            || !is_string($data['t'] ?? null) || $data['t'] === ''
            || !is_string($data['n'] ?? null) || strlen($data['n']) < 16
            || !is_int($data['e'] ?? null) || $data['e'] < $now || $data['e'] > $now + self::MAX_TTL_SECONDS) {
            return null;
        }

        // Once only: the nonce is remembered until the token would have expired anyway.
        if (!Cache::add('voxpilot-sso:'.$data['n'], 1, self::MAX_TTL_SECONDS + 60)) {
            return null;
        }

        return $data['t'];
    }
}
