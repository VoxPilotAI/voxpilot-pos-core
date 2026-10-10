<?php

declare(strict_types=1);

namespace Igniter\VoxPilot;

use Igniter\Admin\Facades\Template;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\Admin\Http\Controllers\Dashboard;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\System\Helpers\MailHelper;
use Igniter\System\Classes\BaseExtension;
use Igniter\VoxPilot\Console\ApplyBranding;
use Igniter\VoxPilot\DashboardWidgets;
use Igniter\VoxPilot\Console\BootstrapTenant;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantForAdmin;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantFromToken;
use Igniter\VoxPilot\Http\Middleware\StorefrontLanding;
use Igniter\VoxPilot\Http\Middleware\VerifyHmacSignature;
use Igniter\VoxPilot\Http\Middleware\VerifyProvisioningSecret;
use Igniter\VoxPilot\Jobs\NotifyStatusChange;
use Igniter\VoxPilot\Mail\LocalizedMailHelper;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Scopes\TenantLocationScope;
use Igniter\VoxPilot\Scopes\TenantOrderScope;
use Igniter\VoxPilot\Services\LanguagePreference;
use Igniter\VoxPilot\Services\TenantContext;
use Igniter\VoxPilot\Support\Locale;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Override;

class Extension extends BaseExtension
{
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
        $this->registerStaffMembership();
    }

    #[Override]
    public function registerDashboardWidgets(): array
    {
        return [
            DashboardWidgets\Overview::class => ['code' => 'vp_overview', 'label' => 'igniter.voxpilot::dashboard.widget_overview'],
            DashboardWidgets\OrdersByHour::class => ['code' => 'vp_orders_by_hour', 'label' => 'igniter.voxpilot::dashboard.widget_orders_by_hour'],
            DashboardWidgets\Assistant::class => ['code' => 'vp_assistant', 'label' => 'igniter.voxpilot::dashboard.widget_assistant'],
            DashboardWidgets\RecentOrders::class => ['code' => 'vp_recent_orders', 'label' => 'igniter.voxpilot::dashboard.widget_recent_orders'],
            DashboardWidgets\TopItems::class => ['code' => 'vp_top_items', 'label' => 'igniter.voxpilot::dashboard.widget_top_items'],
            DashboardWidgets\DaySummary::class => ['code' => 'vp_day_summary', 'label' => 'igniter.voxpilot::dashboard.widget_day_summary'],
        ];
    }

    /**
     * The VoxPilot dashboard replaces TastyIgniter's default one (onboarding checklist, TastyIgniter
     * news, generic stats). Users who saved their own layout keep it until they reset it.
     */
    protected function registerDefaultDashboard(): void
    {
        Dashboard::extend(function (Dashboard $controller): void {
            $controller->containerConfig['defaultWidgets'] = [
                'vp_overview' => ['widget' => 'vp_overview', 'priority' => 10, 'width' => '12'],
                'vp_orders_by_hour' => ['widget' => 'vp_orders_by_hour', 'priority' => 20, 'width' => '8'],
                'vp_assistant' => ['widget' => 'vp_assistant', 'priority' => 30, 'width' => '4'],
                'vp_recent_orders' => ['widget' => 'vp_recent_orders', 'priority' => 40, 'width' => '8'],
                'vp_top_items' => ['widget' => 'vp_top_items', 'priority' => 50, 'width' => '4'],
                'vp_day_summary' => ['widget' => 'vp_day_summary', 'priority' => 60, 'width' => '12'],
            ];
        });
    }

    #[Override]
    public function registerNavigation(): array
    {
        return [
            'voxpilot-board' => [
                'priority' => 5,
                'class' => 'voxpilot-board',
                'icon' => 'fa-table-columns',
                'href' => admin_url('igniter/voxpilot/board'),
                'title' => lang('igniter.voxpilot::board.nav'),
                'permission' => 'Admin.Orders',
            ],
            'voxpilot-live' => [
                'priority' => 6,
                'class' => 'voxpilot-incoming-orders',
                'icon' => 'fa-tower-broadcast',
                'href' => admin_url('igniter/voxpilot/incoming_orders'),
                'title' => lang('igniter.voxpilot::board.nav_live'),
                'permission' => 'Igniter.VoxPilot.Manage',
            ],
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
        $this->registerDefaultDashboard();
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
            .'document.documentElement.setAttribute("data-bs-theme",t)})();</script>'
            // Installable POS (kitchen tablets, phones): manifest + /vp-sw.js (registered by vp-admin.js).
            .'<link rel="manifest" href="'.e(asset('voxpilot/manifest.json')).'">'
            .'<meta name="theme-color" content="#5b4cf0"><meta name="mobile-web-app-capable" content="yes">'
            .'<link rel="apple-touch-icon" href="'.e(asset('voxpilot/apple-touch-icon.png')).'">');

        Template::registerHook('endStyles', fn () => '<link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">'
            .'<link rel="stylesheet" href="'.e(asset('voxpilot/admin/vp-admin.css')).'?v='.self::assetVersion('vp-admin.css').'">');

        Template::registerHook('endScripts', fn () => '<script src="'.e(asset('voxpilot/admin/vp-admin.js')).'?v='.self::assetVersion('vp-admin.js').'"></script>');
        Template::registerHook('endScripts', fn () => $this->renderLanguageMenu());
    }

    /** The header language switcher (moved into the header by vp-admin.js). */
    protected function renderLanguageMenu(): string
    {
        $user = AdminAuth::user();
        if (!$user) {
            return '';
        }
        $tenant = app(TenantContext::class)->tenant();
        $preference = new LanguagePreference();

        return view('igniter.voxpilot::_partials.languagemenu', [
            'languages' => Locale::options(),
            'current' => Locale::normalize($user->getLocale() ?? app()->getLocale()),
            'canTeam' => $tenant && $preference->canSetForTenant($user, $tenant),
            'tenantLocale' => $tenant ? $preference->tenantLocale($tenant) : null,
        ])->render();
    }

    /**
     * Staff created from the admin by a restaurant's owner belong to that restaurant (tenant
     * membership), and start in the restaurant's language.
     */
    protected function registerStaffMembership(): void
    {
        User::created(function (User $user): void {
            $tenant = app(TenantContext::class)->tenant();
            if ($tenant && AdminAuth::isLogged()) {
                TenantMembership::firstOrCreate(['tenant_id' => $tenant->id, 'user_id' => $user->user_id], ['role' => 'staff']);
            }
        });

        TenantMembership::created(fn (TenantMembership $membership) => (new LanguagePreference())->applyTenantDefault($membership));
    }

    /** Cache-buster for public/voxpilot/admin files: their modification time. */
    protected static function assetVersion(string $file): string
    {
        return (string) (@filemtime(public_path('voxpilot/admin/'.$file)) ?: '1');
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
