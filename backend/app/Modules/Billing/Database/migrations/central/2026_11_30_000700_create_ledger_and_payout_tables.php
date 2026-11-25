<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Central finance ledger (decision #72), USD minor units:
|   ledger_transactions — one per source event (order paid / status, refund completed,
|                         settlement paid, payout paid / cancelled, invoice paid), unique
|                         `idempotency_key`; `net_amount` = signed effect on what the
|                         platform owes the store, `balance_after` = running store net;
|   ledger_entries      — the balanced debit / credit lines of a transaction per account
|                         (vendor_payable, vendor_receivable, platform_cash, platform_revenue);
|   tenant_payouts      — platform → store payouts (ADM-052), scheduled → processing → paid,
|                         or cancelled.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('source', 24);
            $table->string('source_reference', 64)->nullable();
            $table->uuid('order_uuid')->nullable();
            $table->string('direction', 12);
            $table->bigInteger('amount');
            $table->bigInteger('net_amount');
            $table->bigInteger('balance_after');
            $table->string('currency', 3)->default('USD');
            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['source', 'direction']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('order_uuid')->nullable();
            $table->string('order_number', 32)->nullable();
            $table->string('account', 24);
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'account']);
            $table->index(['tenant_id', 'order_uuid', 'account']);
        });

        Schema::create('tenant_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('method', 24);
            $table->string('status', 16);
            $table->date('scheduled_for')->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->boolean('notify_vendor')->default(false);
            $table->json('payee')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payouts');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
    }
};
