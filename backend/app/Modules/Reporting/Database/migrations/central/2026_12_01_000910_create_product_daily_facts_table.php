<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Product performance facts (ADM-070j): one row per store product per day, incremented by
| Reporting\Listeners\RecordProductActivity from Storefront ProductViewed, Commerce
| CartItemAdded and OrderPaid. Money in USD minor units.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_daily_facts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_product_id');
            $table->date('day');
            $table->string('product_name', 255)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('cart_adds')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('units')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'store_product_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_daily_facts');
    }
};
