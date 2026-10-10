<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Services;

use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Support\Locale;

/**
 * Admin language. TastyIgniter shows the admin in each staff member's language (their
 * `language_id`); the restaurant's language (`tenants.settings.locale`) is the one the owner sets for
 * the whole team, and the one new staff start with.
 */
class LanguagePreference
{
    public function setForUser(User $user, string $code): void
    {
        $user->language_id = Locale::language($code)->getKey();
        $user->save();
    }

    /** The restaurant's language, applied to every staff member of the restaurant. */
    public function setForTenant(Tenant $tenant, string $code): void
    {
        $settings = (array) $tenant->settings;
        $settings['locale'] = $code;
        $tenant->settings = $settings;
        $tenant->save();

        $languageId = Locale::language($code)->getKey();
        User::query()
            ->whereIn('user_id', TenantMembership::where('tenant_id', $tenant->id)->pluck('user_id'))
            ->update(['language_id' => $languageId]);
    }

    public function tenantLocale(Tenant $tenant): ?string
    {
        return Locale::match(((array) $tenant->settings)['locale'] ?? null);
    }

    /** Only the restaurant's owner (or a platform super user) changes the whole team's language. */
    public function canSetForTenant(User $user, Tenant $tenant): bool
    {
        return (bool) $user->super_user
            || TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $user->user_id)->where('role', 'owner')->exists();
    }

    /** New staff start in the restaurant's language (when it has one and they chose none). */
    public function applyTenantDefault(TenantMembership $membership): void
    {
        $tenant = $membership->tenant;
        $user = $membership->user;
        $code = $tenant ? $this->tenantLocale($tenant) : null;
        if ($code && $user && !$user->language_id) {
            $this->setForUser($user, $code);
        }
    }
}
