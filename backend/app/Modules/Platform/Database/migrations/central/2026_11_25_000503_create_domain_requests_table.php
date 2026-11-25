<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom-domain requests. Only a connected request has a row in the tenancy `domains`
 * table (domain_id), so pending / removed hosts never resolve to the store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('host');
            $table->string('status', 16)->default('pending')->index();
            $table->string('verification_token', 64);
            $table->string('dns_status', 16)->default('unchecked');
            $table->json('dns_result')->nullable();
            $table->string('ssl_status', 16)->default('unknown');
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index('host');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('dns_record_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 8);
            $table->string('host', 191);
            $table->string('value', 512);
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedSmallInteger('priority')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_record_templates');
        Schema::dropIfExists('domain_requests');
    }
};
