<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voxpilot_order_metadata', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('order_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('location_id');
            $table->string('external_order_id');
            $table->string('call_sid')->nullable();
            $table->string('source', 50)->default('voice');
            $table->text('transcript')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['tenant_id', 'external_order_id']);
            $table->index(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voxpilot_order_metadata');
    }
};
