<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\VoxPilot\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Signed POS → VoxPilot events (order status, menu and store changes). The body is signed as sent:
 * `X-VoxPilot-Signature: v1=hmac_sha256("{timestamp}.{body}", hmac_shared_secret)`, checked by
 * VoxPilot's public webhook before it forwards the event to its order gateway.
 */
class VoxPilotWebhook
{
    /** VOXPILOT_API_URL when the POS reaches VoxPilot through an internal address, else the public API URL given at provisioning. */
    public static function url(Tenant $tenant, string $path): ?string
    {
        $base = config('voxpilot.api_url') ?: (($tenant->settings ?? [])['webhook_callback_url'] ?? null);
        if (!$base) {
            return null;
        }
        $url = rtrim((string) $base, '/').$path;
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /** Null when the tenant has no VoxPilot URL (nothing to notify). */
    public function send(Tenant $tenant, string $path, string $event, array $payload): ?Response
    {
        $url = self::url($tenant, $path);
        if (!$url) {
            Log::info("[voxpilot] {$event} skipped: no VoxPilot URL for tenant {$tenant->id}");

            return null;
        }

        $body = json_encode($payload);
        $timestamp = time();
        $headers = ['Content-Type' => 'application/json', 'X-VoxPilot-Event' => $event];
        if ($secret = config('voxpilot.hmac_shared_secret')) {
            $headers['X-VoxPilot-Signature'] = 'v1='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
            $headers['X-VoxPilot-Timestamp'] = (string) $timestamp;
        }

        return Http::withHeaders($headers)->timeout(10)->withBody($body, 'application/json')->post($url);
    }
}
