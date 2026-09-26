<?php

declare(strict_types=1);

namespace Igniter\VoxPilot;

use Igniter\System\Classes\BaseExtension;
use Igniter\VoxPilot\Console\BootstrapTenant;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantFromToken;
use Igniter\VoxPilot\Http\Middleware\VerifyProvisioningSecret;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Support\Facades\Broadcast;
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
        $this->registerApiRoutes();
        $this->registerProvisioningRoutes();
        $this->registerChannels();
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

    protected function registerApiRoutes(): void
    {
        Route::prefix('api/voxpilot')
            ->middleware(['api', ResolveTenantFromToken::class])
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
}
