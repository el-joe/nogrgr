<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central-website sign-ups not yet turned into a store. The completion link carries a
 * random token (stored as a SHA-256 hash only) inside a signed URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('locale', 8)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('term', 16)->nullable();
            $table->string('status', 32)->default('email_unverified')->index();
            $table->string('owner_name')->nullable();
            $table->string('password')->nullable();
            $table->string('store_name')->nullable();
            $table->string('slug', 63)->nullable()->index();
            $table->string('custom_domain')->nullable();
            $table->unsignedInteger('profit_percent_bps')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('link_sent_at')->nullable();
            $table->unsignedSmallInteger('link_sent_count')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_registrations');
    }
};
