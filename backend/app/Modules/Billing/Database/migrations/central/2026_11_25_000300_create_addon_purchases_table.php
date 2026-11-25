<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid add-ons bought by a store (languages, AI translation). Created as
 * "pending_payment"; the payment gateways (P5) settle them through
 * AddonPurchaseService::markPaid(). Amounts are USD minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('item_code', 32);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('pending_payment')->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_purchases');
    }
};
