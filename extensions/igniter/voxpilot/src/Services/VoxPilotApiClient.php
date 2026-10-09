<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Igniter\VoxPilot\Models\Installation;
use Igniter\VoxPilot\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Signed reads from VoxPilot for the current tenant (POS dashboard). Each request is HMAC-signed
 * with the shared VoxPilot secret over timestamp, method, path+query, the tenant's VoxPilot id and
 * this installation's connection id; VoxPilot answers only for that tenant's connected assistant.
 */
class VoxPilotApiClient
{
    private const CACHE_SECONDS = 60;

    public function __construct(protected TenantContext $tenantContext) {}

    /** VoxPilot's assistant figures for the range, or null when not connected or unavailable. */
    public function assistantStats(DateTimeInterface $from, DateTimeInterface $to): ?array
    {
        $tenant = $this->tenantContext->tenant();
        if (!$tenant instanceof Tenant) {
            return null;
        }

        $installation = Installation::where('tenant_id', $tenant->id)->first();
        $connectionId = (string) ($installation?->voxpilot_connection_id ?? '');
        $baseUrl = rtrim((string) (config('voxpilot.api_url') ?: ($tenant->settings['webhook_callback_url'] ?? '')), '/');
        $secret = (string) config('voxpilot.hmac_shared_secret');
        if (!$installation?->isConnected() || $connectionId === '' || $baseUrl === '' || $secret === '') {
            return null;
        }

        $query = http_build_query([
            'from' => CarbonImmutable::instance($from)->utc()->format('Y-m-d\TH:i:s\Z'),
            'to' => CarbonImmutable::instance($to)->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
        $path = '/pos/assistant-stats?'.$query;

        return Cache::remember('voxpilot:assistant-stats:'.$tenant->id.':'.md5($query), self::CACHE_SECONDS, function () use ($tenant, $connectionId, $baseUrl, $secret, $path) {
            $timestamp = (string) time();
            $signed = implode("\n", [$timestamp, 'GET', $path, (string) $tenant->external_tenant_id, $connectionId]);

            try {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->withHeaders([
                        'X-VoxPilot-Tenant' => (string) $tenant->external_tenant_id,
                        'X-VoxPilot-Connection' => $connectionId,
                        'X-VoxPilot-Timestamp' => $timestamp,
                        'X-VoxPilot-Signature' => 'v1='.hash_hmac('sha256', $signed, $secret),
                    ])
                    ->get($baseUrl.$path);
            } catch (\Throwable $e) {
                Log::warning('VoxPilot assistant stats unavailable', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

                return null;
            }

            if (!$response->successful()) {
                Log::warning('VoxPilot assistant stats refused', ['tenant_id' => $tenant->id, 'status' => $response->status()]);

                return null;
            }

            return $response->json('data');
        });
    }
}
