<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One POS order per VoxPilot order: two deliveries of the same order arriving at the same time
 * could both pass the idempotency check and create the order twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voxpilot_order_metadata', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'external_order_id'], 'voxpilot_order_metadata_tenant_external_unique');
            $table->dropIndex(['tenant_id', 'external_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('voxpilot_order_metadata', function (Blueprint $table): void {
            $table->index(['tenant_id', 'external_order_id']);
            $table->dropUnique('voxpilot_order_metadata_tenant_external_unique');
        });
    }
};
