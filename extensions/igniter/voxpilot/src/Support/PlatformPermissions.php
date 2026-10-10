<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

/**
 * TastyIgniter permissions over data every restaurant shares (order statuses, site settings,
 * languages, payment gateways, mail templates, roles, media, extensions…). Only platform super users
 * hold them: a restaurant's staff changing them would change every restaurant.
 */
final class PlatformPermissions
{
    public const CODES = [
        'Admin.Extensions',
        'Admin.MailTemplates',
        'Admin.MediaManager',
        'Admin.Payments',
        'Admin.Statuses',
        'Admin.SystemInfo',
        'Admin.SystemLogs',
        'Admin.CustomerGroups',
        'Admin.StaffGroups',
        'Admin.StaffRoles',
        'Admin.Impersonate',
        'Admin.ImpersonateCustomers',
        'Igniter.Api.Manage',
        'Igniter.Automation.Manage',
        'Igniter.FrontEnd.ManageBanners',
        'Igniter.FrontEnd.ManageSettings',
        'Igniter.FrontEnd.ManageSlideshow',
        'Igniter.Pages.Manage',
        'Igniter.Socialite.Manage',
        'Site.Countries',
        'Site.Currencies',
        'Site.Languages',
        'Site.Settings',
        'Site.Themes',
        'Site.Updates',
    ];

    /** A role's permissions (code => 1) without the platform ones. */
    public static function without(array $permissions): array
    {
        return array_diff_key($permissions, array_flip(self::CODES));
    }
}
