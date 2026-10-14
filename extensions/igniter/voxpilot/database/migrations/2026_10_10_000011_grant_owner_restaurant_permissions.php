<?php

declare(strict_types=1);

use Igniter\VoxPilot\Services\TenantProvisioningService;
use Illuminate\Database\Migrations\Migration;

/**
 * Restaurant data is now scoped per restaurant, so the owner role of the restaurants already
 * provisioned gets what new ones get: their menu, locations, staff, customers and dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(TenantProvisioningService::class)->ensureOwnerRoleWithVoxPilotPermission();
    }

    public function down(): void {}
};
