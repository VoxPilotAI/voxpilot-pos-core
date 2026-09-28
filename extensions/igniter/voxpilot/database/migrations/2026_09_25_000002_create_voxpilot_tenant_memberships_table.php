<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voxpilot_tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->string('role', 50)->default('member');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('user_id');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('voxpilot_tenants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voxpilot_tenant_memberships');
    }
};
