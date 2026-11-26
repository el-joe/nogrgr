<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| order_facts for Admin › Reports and Shoppers (P9, ADM-070 / ADM-043): the shopper's
| e-mail (guest shoppers have no customer id; shown masked) and the coupon code (coupon
| impact by campaign). Composite index for the period × store aggregates.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_facts', function (Blueprint $table): void {
            $table->string('customer_email', 120)->nullable();
            $table->string('coupon_code', 40)->nullable();
            $table->index(['tenant_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_facts', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'placed_at']);
            $table->dropColumn(['customer_email', 'coupon_code']);
        });
    }
};
