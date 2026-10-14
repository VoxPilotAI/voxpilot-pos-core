<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\VoxPilot\Services\LanguagePreference;
use Igniter\VoxPilot\Services\TenantContext;
use Igniter\VoxPilot\Support\Locale;
use Illuminate\Http\RedirectResponse;

/**
 * Language switcher of the admin header (any signed-in staff member, for themselves); the owner
 * can apply it to the whole restaurant.
 */
class Language extends AdminController
{
    public function onSetLanguage(): RedirectResponse
    {
        $code = Locale::match((string) request()->input('language'));
        $user = $this->getUser();
        if (!$code || !$user) {
            return back(fallback: admin_url('dashboard'));
        }

        $preference = new LanguagePreference();
        $preference->setForUser($user, $code);

        $tenant = app(TenantContext::class)->tenant();
        if ($tenant && request()->boolean('team') && $preference->canSetForTenant($user, $tenant)) {
            $preference->setForTenant($tenant, $code);
        }

        app()->setLocale($code);
        flash()->success(lang('igniter.voxpilot::language.saved'));

        return back(fallback: admin_url('dashboard'));
    }
}
