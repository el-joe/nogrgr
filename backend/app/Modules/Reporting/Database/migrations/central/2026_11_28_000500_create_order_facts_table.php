<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Cross-tenant order reporting row (architecture: central facts fed by events). One row
| per store order, upserted by Reporting\Listeners\RecordOrderFacts on every Commerce
| order event; admin lists and reports read it instead of opening each tenant database.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_facts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('order_uuid');
            $table->string('order_number', 32);
            $table->string('kind', 16);
            $table->string('status', 24)->index();
            $table->string('payment_status', 16);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable()->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('items_count')->default(0);
            $table->bigInteger('items_grand_total')->default(0);
            $table->bigInteger('flash_discount')->default(0);
            $table->bigInteger('shipping_charge')->default(0);
            $table->bigInteger('coupon_discount')->default(0);
            $table->string('coupon_source', 16)->nullable();
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('platform_profit')->default(0);
            $table->bigInteger('vendor_profit')->default(0);
            $table->string('payment_gateway', 32)->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'order_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_facts');
    }
};
