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
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('order_id');
            $table->index('tenant_id');
        });

        // Backfill: derive tenant from location
        DB::statement('
            UPDATE ' . DB::getTablePrefix() . 'orders o
            JOIN ' . DB::getTablePrefix() . 'locations l ON o.location_id = l.location_id
            SET o.tenant_id = l.tenant_id
            WHERE o.tenant_id IS NULL AND l.tenant_id IS NOT NULL
        ');

        // Orders without location get default tenant
        $defaultTenantId = DB::table('voxpilot_tenants')->value('id');
        if ($defaultTenantId) {
            DB::table('orders')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
