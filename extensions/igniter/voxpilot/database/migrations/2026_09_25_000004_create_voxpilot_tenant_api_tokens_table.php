<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voxpilot_tenant_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('default_location_id')->nullable();
            $table->string('name');
            $table->string('token_hash', 64);
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique('token_hash');
            $table->index('tenant_id');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('voxpilot_tenants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voxpilot_tenant_api_tokens');
    }
};
