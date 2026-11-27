<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gateway limits (ADM-100c, MAIL-023 / MAIL-024, decision #124), central:
|   payment_gateways.monthly_store_limit_minor  per-store monthly volume (USD cents) a store
|                                              may take through the PLATFORM account of the
|                                              gateway (null = no limit);
|   payment_gateways.limit_warn_percent         warning level (default 80 %); the block is 100 %;
|   gateway_limit_usages                         volume per store × gateway × month ("2026-12")
|                                              with the one-time warned_at / blocked_at marks.
| Stores using their own gateway keys are never counted.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->unsignedBigInteger('monthly_store_limit_minor')->nullable()->after('fee_fixed_minor');
            $table->unsignedTinyInteger('limit_warn_percent')->default(80)->after('monthly_store_limit_minor');
        });

        Schema::create('gateway_limit_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('gateway', 32);
            $table->char('period', 7);
            $table->unsignedBigInteger('volume_minor')->default(0);
            $table->timestamp('warned_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'gateway', 'period']);
            $table->index(['period', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_limit_usages');

        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->dropColumn(['monthly_store_limit_minor', 'limit_warn_percent']);
        });
    }
};
