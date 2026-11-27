<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance windows (ADM-113): platform-wide (central website + every storefront +
 * Store API), all storefronts, or one store (tenant_id). ends_at null = until ended;
 * ended_at = "End now". message = {en, ar}; allowed_ips = IPs / CIDR ranges that bypass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('scope', 20);
            $table->string('title', 120);
            $table->string('notes', 255)->nullable();
            $table->json('message')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['ended_at', 'starts_at']);
        });

        Schema::create('cache_clear_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('status', 16);
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('stores')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['action', 'tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_clear_runs');
        Schema::dropIfExists('maintenance_windows');
    }
};
