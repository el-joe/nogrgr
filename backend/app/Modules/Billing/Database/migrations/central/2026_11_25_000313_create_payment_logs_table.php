<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway attempts for platform charges (old system `payment_logs`). Amount in USD
 * minor units; payload = raw gateway webhook/response for support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('gateway', 32)->index();
            $table->string('purpose', 32);
            $table->string('status', 16)->index();
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3)->default('USD');
            $table->string('gateway_reference')->nullable()->index();
            $table->json('payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
