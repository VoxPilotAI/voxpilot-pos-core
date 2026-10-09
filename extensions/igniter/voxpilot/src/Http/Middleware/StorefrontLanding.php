<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Middleware;

use Closure;
use Igniter\VoxPilot\Support\Locale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The VoxPilot POS has restaurant owners, not diners. The storefront home renders the VoxPilot POS
 * landing page, and the customer login/register pages redirect to the admin sign-in.
 */
class StorefrontLanding
{
    private const LANDING_ROUTES = ['igniter.theme.home'];

    /** Signed-out admin screens: shown in the browser's language (TastyIgniter uses the default). */
    private const ADMIN_AUTH_ROUTES = ['igniter.admin.login', 'igniter.admin.reset'];

    private const ADMIN_LOGIN_ROUTES = [
        'igniter.theme.account.login',
        'igniter.theme.account.register',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::ADMIN_AUTH_ROUTES, true) && !app('admin.auth')->isLogged()) {
            app()->setLocale(Locale::normalize($request->getPreferredLanguage(Locale::supported())));
        }

        if (!config('voxpilot.storefront_landing', true) || !$request->isMethod('GET')) {
            return $next($request);
        }

        if (in_array($routeName, self::ADMIN_LOGIN_ROUTES, true)) {
            return redirect()->to(admin_url('login'));
        }

        if (in_array($routeName, self::LANDING_ROUTES, true)) {
            return $this->landing($request);
        }

        return $next($request);
    }

    protected function landing(Request $request): Response
    {
        $locale = $request->query('lang')
            ? Locale::normalize((string) $request->query('lang'))
            : Locale::normalize($request->getPreferredLanguage(Locale::supported()));

        app()->setLocale($locale);

        return response()->view('igniter.voxpilot::landing', [
            'locale' => $locale,
            'brandName' => config('voxpilot.brand_name', 'VoxPilot POS'),
            'loginUrl' => admin_url('login'),
            'marketingUrl' => config('voxpilot.marketing_url', 'https://voxpilothq.io'),
        ])->header('Vary', 'Accept-Language');
    }
}
