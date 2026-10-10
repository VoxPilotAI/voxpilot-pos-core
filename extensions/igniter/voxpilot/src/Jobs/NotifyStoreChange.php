<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Jobs;

use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Services\VoxPilotWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tells VoxPilot that a location's menu availability (`menu.changed`) or store status
 * (`store.changed`: busy, paused, delivery/pickup) changed, so the order gateway drops the menu it
 * cached for calls and the next call reads the fresh one (pos-gateway SPEC-001). The event carries
 * no data: VoxPilot reads the menu again with its own POS token.
 */
class NotifyStoreChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const EVENTS = ['menu.changed', 'store.changed'];

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        protected int $tenantId,
        protected int $locationId,
        protected string $event,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant || !in_array($this->event, self::EVENTS, true)) {
            return;
        }

        $response = (new VoxPilotWebhook())->send($tenant, '/pos/webhooks/store-changed', $this->event, [
            'event' => $this->event,
            'tenant_id' => $tenant->external_tenant_id,
            'location_id' => $this->locationId,
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($response && $response->failed()) {
            Log::warning("[voxpilot] {$this->event} failed: HTTP {$response->status()} for tenant {$this->tenantId}");
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
