<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voxpilot_auth_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code_hash', 64)->unique();
            $table->string('state', 128);
            $table->string('redirect_uri', 2048)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at']);
            $table->foreign('tenant_id')->references('id')->on('voxpilot_tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voxpilot_auth_codes');
    }
};
