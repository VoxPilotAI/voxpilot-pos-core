<?php

declare(strict_types=1);

namespace Igniter\VoxPilot;

use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\System\Classes\BaseExtension;
use Igniter\VoxPilot\Console\BootstrapTenant;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantForAdmin;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantFromToken;
use Igniter\VoxPilot\Http\Middleware\VerifyHmacSignature;
use Igniter\VoxPilot\Http\Middleware\VerifyProvisioningSecret;
use Igniter\VoxPilot\Jobs\NotifyStatusChange;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Scopes\TenantLocationScope;
use Igniter\VoxPilot\Scopes\TenantOrderScope;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Override;

class Extension extends BaseExtension
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->mergeConfigFrom(__DIR__.'/../config/voxpilot.php', 'voxpilot');

        $this->registerConsoleCommand('voxpilot.bootstrap-tenant', BootstrapTenant::class);
    }

    #[Override]
    public function boot(): void
    {
        $this->registerAdminMiddleware();
        $this->registerTenantScopes();
        $this->registerApiRoutes();
        $this->registerProvisioningRoutes();
        $this->registerChannels();
        $this->registerOrderStatusListener();
    }

    #[Override]
    public function registerNavigation(): array
    {
        return [
            'tools' => [
                'child' => [
                    'voxpilot' => [
                        'priority' => 50,
                        'class' => 'voxpilot-integrations',
                        'href' => admin_url('igniter/voxpilot/integrations'),
                        'title' => 'VoxPilot Tokens',
                        'permission' => 'Igniter.VoxPilot.Manage',
                    ],
                    'voxpilot-incoming' => [
                        'priority' => 49,
                        'class' => 'voxpilot-incoming-orders',
                        'href' => admin_url('igniter/voxpilot/incoming_orders'),
                        'title' => 'Incoming Orders',
                        'permission' => 'Igniter.VoxPilot.Manage',
                    ],
                ],
            ],
        ];
    }

    #[Override]
    public function registerPermissions(): array
    {
        return [
            'Igniter.VoxPilot.Manage' => [
                'description' => 'Manage VoxPilot integration tokens and settings',
                'group' => 'igniter::system.permissions.name',
            ],
        ];
    }

    protected function registerAdminMiddleware(): void
    {
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('igniter', ResolveTenantForAdmin::class);
    }

    protected function registerTenantScopes(): void
    {
        Order::addGlobalScope(new TenantOrderScope());
        Location::addGlobalScope(new TenantLocationScope());
    }

    protected function registerApiRoutes(): void
    {
        Route::prefix('api/voxpilot')
            ->middleware(['api', VerifyHmacSignature::class, ResolveTenantFromToken::class])
            ->group(__DIR__.'/../routes/api.php');
    }

    protected function registerProvisioningRoutes(): void
    {
        Route::prefix('api/voxpilot/provision')
            ->middleware(['api', VerifyProvisioningSecret::class])
            ->group(__DIR__.'/../routes/provisioning.php');
    }

    protected function registerChannels(): void
    {
        if (!Broadcast::getFacadeRoot()) {
            return;
        }

        Broadcast::routes(['middleware' => ['web', 'igniter']]);

        require __DIR__.'/../routes/channels.php';
    }

    protected function registerOrderStatusListener(): void
    {
        Event::listen('igniter.cart.orderStatusAdded', function (Order $order, $statusHistory): void {
            $metadata = VoxPilotOrderMetadata::where('order_id', $order->order_id)->first();
            if (!$metadata) {
                return;
            }

            NotifyStatusChange::dispatch(
                $order->order_id,
                $metadata->tenant_id,
                $metadata->external_order_id,
                $statusHistory->status?->status_name ?? $statusHistory->status_for ?? 'unknown',
                $statusHistory->comment ?? null,
            );
        });
    }
}
