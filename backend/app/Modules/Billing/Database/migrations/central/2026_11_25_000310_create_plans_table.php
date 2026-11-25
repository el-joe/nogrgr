<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans (old system `packages`). Prices per term in USD minor units;
 * limits = {PlanLimit value: int|null (null = unlimited)}; features = enabled PlanFeature values.
 * name / description / highlights are translatable JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('highlights')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('monthly_price_minor')->default(0);
            $table->unsignedInteger('yearly_price_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
