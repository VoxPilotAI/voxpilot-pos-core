<?php

declare(strict_types=1);

namespace Igniter\VoxPilot;

use Igniter\Admin\Facades\Template;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\System\Helpers\MailHelper;
use Igniter\System\Classes\BaseExtension;
use Igniter\VoxPilot\Console\ApplyBranding;
use Igniter\VoxPilot\Console\BootstrapTenant;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantForAdmin;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantFromToken;
use Igniter\VoxPilot\Http\Middleware\StorefrontLanding;
use Igniter\VoxPilot\Http\Middleware\VerifyHmacSignature;
use Igniter\VoxPilot\Http\Middleware\VerifyProvisioningSecret;
use Igniter\VoxPilot\Jobs\NotifyStatusChange;
use Igniter\VoxPilot\Mail\LocalizedMailHelper;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Scopes\TenantLocationScope;
use Igniter\VoxPilot\Scopes\TenantOrderScope;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Override;

class Extension extends BaseExtension
{
    /** Cache-buster for the admin skin assets; bump when public/voxpilot/admin changes. */
    private const SKIN_VERSION = '1';

    #[Override]
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        // Staff emails go out in the staff member's language (see LocalizedMailHelper).
        $this->app->bind(MailHelper::class, LocalizedMailHelper::class);

        $this->mergeConfigFrom(__DIR__.'/../config/voxpilot.php', 'voxpilot');

        $this->registerConsoleCommand('voxpilot.bootstrap-tenant', BootstrapTenant::class);
        $this->registerConsoleCommand('voxpilot.brand', ApplyBranding::class);
    }

    #[Override]
    public function boot(): void
    {
        $this->registerAdminMiddleware();
        $this->registerBranding();
        $this->registerTenantScopes();
        $this->registerApiRoutes();
        $this->registerProvisioningRoutes();
        $this->registerOAuthRoutes();
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
                        'title' => 'VoxPilot',
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

    /**
     * Mail layout and button in the VoxPilot email style, used by every POS email.
     */
    public function registerMailLayouts(): array
    {
        return [
            'default' => 'igniter.voxpilot::_mail.layouts.default',
        ];
    }

    public function registerMailPartials(): array
    {
        return [
            'button' => 'igniter.voxpilot::_mail.partials.button',
        ];
    }

    /**
     * VoxPilot POS branding: the storefront landing page, and the owner emails (staff invite and
     * admin password reset) rewritten with translated VoxPilot copy. The overrides resolve before
     * the igniter.user views, so TastyIgniter keeps sending them through its own code paths.
     */
    protected function registerBranding(): void
    {
        View::prependNamespace('igniter.user', __DIR__.'/../resources/views/overrides/igniter.user');

        $this->app['router']->pushMiddlewareToGroup('igniter', StorefrontLanding::class);

        $this->registerAdminSkin();
    }

    /**
     * VoxPilot look for the whole admin (public/voxpilot/admin): light and dark mode on top of
     * Bootstrap 5.3's data-bs-theme. The theme is set before first paint, from the saved choice
     * or the device setting, so pages never flash the wrong colours.
     */
    protected function registerAdminSkin(): void
    {
        Template::registerHook('startHead', fn () => '<script>(function(){var t=null;try{t=localStorage.getItem("vp-theme")}catch(e){}'
            .'if(t!=="light"&&t!=="dark"){t=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"}'
            .'document.documentElement.setAttribute("data-bs-theme",t)})();</script>');

        Template::registerHook('endStyles', fn () => '<link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">'
            .'<link rel="stylesheet" href="'.e(asset('voxpilot/admin/vp-admin.css')).'?v='.self::SKIN_VERSION.'">');

        Template::registerHook('endScripts', fn () => '<script src="'.e(asset('voxpilot/admin/vp-admin.js')).'?v='.self::SKIN_VERSION.'"></script>');
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

    /** SPEC-011: code exchange + install activate/deactivate (S2S Bearer secret). */
    protected function registerOAuthRoutes(): void
    {
        Route::prefix('api/voxpilot')
            ->middleware(['api', VerifyProvisioningSecret::class])
            ->group(__DIR__.'/../routes/oauth.php');
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
