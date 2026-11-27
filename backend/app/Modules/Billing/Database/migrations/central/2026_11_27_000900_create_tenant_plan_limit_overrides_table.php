<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-store plan limit overrides (ADM-041h): one row per overridden PlanLimit of a
 * store. No row = the plan value applies; `value` null = unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plan_limit_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('limit', 40);
            $table->unsignedInteger('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'limit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plan_limit_overrides');
    }
};
