<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voxpilot_installations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->string('external_tenant_id')->index();
            $table->string('voxpilot_connection_id')->nullable()->index();
            $table->string('assistant_id')->nullable();
            $table->enum('status', [
                'NOT_CONNECTED',
                'INSTALLATION_PENDING',
                'CONNECTED',
                'DISCONNECTED',
                'INSTALLATION_FAILED',
            ])->default('NOT_CONNECTED');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('voxpilot_tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voxpilot_installations');
    }
};
