<?php

declare(strict_types=1);

namespace Igniter\VoxPilot;

use Igniter\System\Classes\BaseExtension;
use Igniter\VoxPilot\Console\BootstrapTenant;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantFromToken;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Support\Facades\Route;
use Override;

class Extension extends BaseExtension
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->registerConsoleCommand('voxpilot.bootstrap-tenant', BootstrapTenant::class);
    }

    #[Override]
    public function boot(): void
    {
        $this->registerApiRoutes();
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
                        'title' => 'VoxPilot',
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
}
