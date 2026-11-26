<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| Affiliate program (P9, ADM-060…063, decision in docs/plan/decisions.md): programme
| columns on `affiliates`, promo codes, clicks, conversions (first paid subscription
| only), payout requests and payouts. Money = USD minor units (cents); percentage
| rates are basis points (100 = 1 %).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('code', 32)->nullable()->unique()->after('tenant_id');
            $table->string('type', 20)->default('independent')->index()->after('code');
            $table->string('commission_type', 20)->nullable()->after('status');
            $table->unsignedInteger('commission_value')->nullable()->after('commission_type');
            $table->bigInteger('balance')->default(0)->after('commission_value');
            $table->bigInteger('total_earned')->default(0)->after('balance');
            $table->bigInteger('total_paid')->default(0)->after('total_earned');
            $table->string('payout_method', 32)->nullable()->after('total_paid');
            $table->string('paypal_email')->nullable()->after('payout_method');
            $table->string('bank_name', 120)->nullable()->after('paypal_email');
            $table->string('bank_account_name', 120)->nullable()->after('bank_name');
            $table->text('bank_iban')->nullable()->after('bank_account_name');
            $table->text('notes')->nullable()->after('bank_iban');
            $table->timestamp('approved_at')->nullable()->after('notes');
        });

        DB::table('affiliates')->whereNull('code')->orderBy('id')->each(function (object $row): void {
            DB::table('affiliates')->where('id', $row->id)->update([
                'code' => Str::upper(Str::random(8)),
                'type' => $row->tenant_id !== null ? 'vendor' : 'independent',
            ]);
        });

        Schema::create('affiliate_promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('discount_type', 20)->default('percentage');
            $table->unsignedInteger('discount_value');
            $table->string('commission_type', 20)->nullable();
            $table->unsignedInteger('commission_value')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['affiliate_id', 'is_active']);
        });

        Schema::create('affiliate_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('affiliate_promo_codes')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('landing_url', 1024)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['affiliate_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('affiliate_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('click_id')->nullable()->constrained('affiliate_clicks')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('affiliate_promo_codes')->nullOnDelete();
            $table->string('source', 20)->default('link');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->unsignedBigInteger('payment_log_id')->nullable()->unique();
            $table->bigInteger('sale_amount')->default(0);
            $table->bigInteger('commission_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->string('commission_type', 20);
            $table->unsignedInteger('commission_value');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('affiliate_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('method', 32);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->string('attachment_name')->nullable();
            $table->unsignedBigInteger('paid_by_id')->nullable();
            $table->string('paid_by_name')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
            $table->index(['affiliate_id', 'paid_at']);
        });

        Schema::create('affiliate_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payout_requests');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_promo_codes');

        Schema::table('affiliates', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropIndex(['type']);
            $table->dropColumn(['code', 'type', 'commission_type', 'commission_value', 'balance', 'total_earned', 'total_paid', 'payout_method', 'paypal_email', 'bank_name', 'bank_account_name', 'bank_iban', 'notes', 'approved_at']);
        });
    }
};
