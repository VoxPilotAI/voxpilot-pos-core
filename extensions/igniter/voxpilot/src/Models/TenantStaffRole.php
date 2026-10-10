<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\User\Models\UserRole;
use Igniter\VoxPilot\Support\PlatformPermissions;

/**
 * The in-memory copy of a restaurant staff member's role for the current request, with the platform
 * permissions taken out (see PlatformPermissions). Roles are shared by every restaurant, so this copy
 * is never written back.
 */
class TenantStaffRole extends UserRole
{
    public static function limitedCopyOf(?UserRole $role, bool $noPermissions = false): self
    {
        $copy = new self();
        if ($role) {
            $copy->setRawAttributes($role->getAttributes(), true);
            $copy->exists = true;
        }
        $copy->permissions = $noPermissions ? [] : PlatformPermissions::without((array) ($role?->permissions ?: []));

        return $copy;
    }

    public function save(?array $options = null): bool
    {
        return false;
    }

    public function delete(): ?bool
    {
        return false;
    }
}
