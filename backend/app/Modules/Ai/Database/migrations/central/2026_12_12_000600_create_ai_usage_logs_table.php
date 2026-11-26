<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token usage of every AI call batch (decision #44 "usage/token logs"): provider,
 * model, feature, the store it ran for (null = platform / admin) and the tokens the
 * provider reported. Read by Admin › Reports › AI usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnUpdate()->nullOnDelete();
            $table->string('feature', 40);
            $table->string('provider', 32);
            $table->string('model', 120);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->string('subject', 120)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
