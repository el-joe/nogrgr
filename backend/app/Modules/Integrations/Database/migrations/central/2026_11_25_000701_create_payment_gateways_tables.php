<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central payment gateways (Admin › Integrations › Payment gateways). One row per
 * `PaymentGatewayCode`; fees are basis points (100 = 1 %) + a fixed USD amount in
 * minor units. Each gateway has two independent configs (`purpose`):
 * vendor_payments (vendors paying the platform) and tenant_registration (plan
 * checkout). Credentials are an encrypted JSON blob, never returned in plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedInteger('fee_percent_bps')->default(0);
            $table->unsignedBigInteger('fee_fixed_minor')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_gateway_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->boolean('is_enabled')->default(false);
            $table->string('mode', 10)->default('test');
            $table->text('credentials')->nullable();
            $table->json('currencies')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_gateway_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
        Schema::dropIfExists('payment_gateways');
    }
};
