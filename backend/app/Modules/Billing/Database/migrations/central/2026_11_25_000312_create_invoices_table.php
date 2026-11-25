<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform invoices to stores (subscriptions, renewals, add-ons). All amounts in USD
 * minor units: subtotal = Σ item totals, tax = subtotal × tax_rate_bp / 10000, total = subtotal + tax.
 * billable = the Subscription / AddonPurchase the invoice charges for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('status', 16)->index();
            $table->nullableMorphs('billable');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedSmallInteger('tax_rate_bp')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_email')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount_minor');
            $table->unsignedInteger('total_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
