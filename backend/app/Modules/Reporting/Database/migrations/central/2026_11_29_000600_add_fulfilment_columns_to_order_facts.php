<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| order_facts for the admin order list and the shipping queue (P5 batch 3): store order
| id, customer name / city, payment source, fulfilment status, carrier + tracking,
| shipping weight, fulfilling branch and the delivery window.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_facts', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_city', 120)->nullable();
            $table->string('payment_source', 16)->nullable();
            $table->string('fulfilment_status', 24)->default('pending')->index();
            $table->unsignedBigInteger('shipping_company_id')->nullable();
            $table->string('shipping_company_name', 120)->nullable();
            $table->string('tracking_number', 64)->nullable();
            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedBigInteger('fulfilment_branch_id')->nullable();
            $table->date('delivery_min_date')->nullable();
            $table->date('delivery_max_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_facts', fn (Blueprint $table) => $table->dropColumn([
            'order_id', 'customer_name', 'customer_city', 'payment_source', 'fulfilment_status', 'shipping_company_id',
            'shipping_company_name', 'tracking_number', 'weight_grams', 'fulfilment_branch_id', 'delivery_min_date', 'delivery_max_date',
        ]));
    }
};
