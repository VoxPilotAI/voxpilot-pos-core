<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Jobs;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Services\VoxPilotStatusNotifier;
use Igniter\VoxPilot\Services\VoxPilotWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $payload = [
            'event' => 'order.status_changed',
            'external_order_id' => $this->externalOrderId,
            'order_id' => $this->orderId,
            'status' => $this->statusName,
            'comment' => $this->statusComment,
            'tenant_id' => $tenant->external_tenant_id,
            'timestamp' => now()->toIso8601String(),
            // The kitchen's estimate, when the staff set one (pos-gateway SPEC-004).
            'ready_at' => $this->readyAt(),
        ];

        $response = (new VoxPilotWebhook())->send($tenant, '/pos/webhooks/order-status', 'order.status_changed', $payload);
        if (!$response) {
            return;
        }

        if ($response->failed()) {
            Log::warning("[voxpilot] status webhook failed: HTTP {$response->status()} for tenant {$this->tenantId}");
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            return;
        }

        Log::info("[voxpilot] status webhook sent: order {$this->orderId} → {$this->statusName}");
    }

    protected function readyAt(): ?string
    {
        $order = Order::withoutGlobalScopes()->find($this->orderId);

        return $order ? VoxPilotStatusNotifier::readyAt($order)?->toIso8601String() : null;
    }
}
