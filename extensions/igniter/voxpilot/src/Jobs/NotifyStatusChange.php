<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Jobs;

use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyStatusChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        protected int $orderId,
        protected int $tenantId,
        protected string $externalOrderId,
        protected string $statusName,
        protected ?string $statusComment,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            Log::warning("[voxpilot] status webhook skipped: tenant {$this->tenantId} not found");
            return;
        }

        $callbackUrl = $this->resolveCallbackUrl($tenant);
        if (!$callbackUrl) {
            return;
        }

        $payload = [
            'event' => 'order.status_changed',
            'external_order_id' => $this->externalOrderId,
            'order_id' => $this->orderId,
            'status' => $this->statusName,
            'comment' => $this->statusComment,
            'tenant_id' => $tenant->external_tenant_id,
            'timestamp' => now()->toIso8601String(),
        ];

        $body = json_encode($payload);
        $timestamp = time();
        $hmacSecret = config('voxpilot.hmac_shared_secret');

        $headers = [
            'Content-Type' => 'application/json',
            'X-VoxPilot-Event' => 'order.status_changed',
        ];

        if ($hmacSecret) {
            $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $hmacSecret);
            $headers['X-VoxPilot-Signature'] = "v1={$signature}";
            $headers['X-VoxPilot-Timestamp'] = (string) $timestamp;
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->withBody($body, 'application/json')
            ->post($callbackUrl);

        if ($response->failed()) {
            Log::warning("[voxpilot] status webhook failed: HTTP {$response->status()} to {$callbackUrl}");
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            return;
        }

        Log::info("[voxpilot] status webhook sent: order {$this->orderId} → {$this->statusName}");
    }

    protected function resolveCallbackUrl(Tenant $tenant): ?string
    {
        $settings = $tenant->settings ?? [];
        $url = $settings['webhook_callback_url'] ?? null;

        if (!$url) {
            Log::info("[voxpilot] status webhook skipped: no callback URL for tenant {$this->tenantId}");
            return null;
        }

        $parsed = parse_url(rtrim($url, '/') . '/pos/webhooks/order-status');
        if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            Log::warning("[voxpilot] status webhook skipped: invalid URL scheme for tenant {$this->tenantId}");
            return null;
        }

        return rtrim($url, '/') . '/pos/webhooks/order-status';
    }
}
