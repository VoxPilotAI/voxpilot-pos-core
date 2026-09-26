<?php

declare(strict_types=1);

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\TenantMembership;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'tenant.{tenantId}.location.{locationId}.orders',
    function ($user, int $tenantId, int $locationId): bool {
        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$membership) {
            return false;
        }

        if ($user->super_user) {
            return true;
        }

        $location = Location::where('location_id', $locationId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $location !== null;
    },
    ['guards' => ['web', 'igniter-admin']],
);
