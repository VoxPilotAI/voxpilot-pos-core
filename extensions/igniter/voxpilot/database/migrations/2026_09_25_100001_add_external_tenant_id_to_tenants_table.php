<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some local databases got this column by hand before the migration existed; adding it
        // again would fail and block every later VoxPilot migration.
        if (Schema::hasColumn('voxpilot_tenants', 'external_tenant_id')) {
            return;
        }

        Schema::table('voxpilot_tenants', function (Blueprint $table): void {
            $table->string('external_tenant_id')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('voxpilot_tenants', function (Blueprint $table): void {
            $table->dropUnique(['external_tenant_id']);
            $table->dropColumn('external_tenant_id');
        });
    }
};
