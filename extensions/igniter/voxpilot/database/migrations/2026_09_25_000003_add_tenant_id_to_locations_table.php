<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::getTablePrefix();

        Schema::table('locations', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('location_id');
            $table->index('tenant_id');
        });

        // Backfill: assign all existing locations to the first tenant
        $defaultTenantId = DB::table('voxpilot_tenants')->value('id');
        if ($defaultTenantId) {
            DB::table('locations')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
