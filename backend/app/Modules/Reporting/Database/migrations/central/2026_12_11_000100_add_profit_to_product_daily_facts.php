<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Vendor product profitability (VEN-045d): the store's share of each paid line
| (Commerce\Pricing\PricedLine::vendorShare(), ORDER_FORMULAS §2) summed per store
| product per day, USD minor units. Order-level amounts (shipping, coupons) are not
| allocated to products.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_daily_facts', function (Blueprint $table): void {
            $table->bigInteger('profit')->default(0)->after('revenue');
        });
    }

    public function down(): void
    {
        Schema::table('product_daily_facts', fn (Blueprint $table) => $table->dropColumn('profit'));
    }
};
