<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront checkouts started with the platform's (central) gateway keys. The gateway
 * sends their webhooks to the central endpoint; this row routes an event to the store
 * (tenant) and its payment attempt (order_payments.reference). Own-key checkouts never
 * get a row (they use the store's webhook URL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_checkout_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 40);
            $table->string('checkout_id', 191);
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('payment_reference', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['gateway', 'checkout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_checkout_routes');
    }
};
