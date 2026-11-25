<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Storefront cart, orders and payments (P5 batch 2, decision #68). Tenant DB only.
| Money = USD minor units (bigint). Product refs: store_products id + variant id (central
| variant id for central products, store_product_variants id for own products; 0 = none).
| Orders keep a full snapshot: addresses, every OrderComposer output, shipping quote and
| delivery estimate, so later catalog/price changes never alter a placed order.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->unique();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('coupon_code', 40)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->unsignedBigInteger('store_product_id')->index();
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'store_product_id', 'variant_key']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 32)->unique();
            $table->uuid('checkout_group')->index();
            $table->string('kind', 16);
            $table->string('status', 24)->index();
            $table->string('payment_status', 16)->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_email', 120)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('items_grand_total')->default(0);
            $table->bigInteger('display_subtotal')->default(0);
            $table->bigInteger('flash_discount')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('shipping_charge')->default(0);
            $table->bigInteger('coupon_discount')->default(0);
            $table->string('coupon_source', 16)->nullable();
            $table->string('coupon_code', 40)->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('platform_profit')->default(0);
            $table->bigInteger('vendor_profit')->default(0);
            $table->json('totals');
            $table->json('shipping_quote')->nullable();
            $table->unsignedBigInteger('shipping_company_id')->nullable();
            $table->string('shipping_company_name', 120)->nullable();
            $table->date('delivery_min_date')->nullable();
            $table->date('delivery_max_date')->nullable();
            $table->json('delivery_estimate')->nullable();
            $table->string('payment_gateway', 32)->nullable();
            $table->string('payment_source', 16)->nullable();
            $table->string('display_currency', 3)->nullable();
            $table->decimal('display_rate', 18, 8)->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('tracking_number', 64)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('reservation_expires_at')->nullable()->index();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('line_key', 64);
            $table->unsignedBigInteger('store_product_id')->index();
            $table->string('product_source', 16);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->string('name', 255);
            $table->string('variant_label', 255)->nullable();
            $table->string('sku', 120)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->unsignedInteger('quantity');
            $table->bigInteger('cost');
            $table->bigInteger('profit_amount');
            $table->bigInteger('price');
            $table->bigInteger('sub_total');
            $table->bigInteger('fixed_shipping');
            $table->bigInteger('discount');
            $table->string('discount_source', 16)->nullable();
            $table->unsignedBigInteger('flash_sale_id')->nullable();
            $table->bigInteger('tax');
            $table->bigInteger('shipping_fee');
            $table->bigInteger('grand_total');
            $table->unsignedInteger('weight_grams')->default(0);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->uuid('checkout_group')->index();
            $table->string('gateway', 32);
            $table->string('source', 16);
            $table->string('mode', 8);
            $table->string('status', 16)->index();
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('checkout_id', 255)->nullable()->index();
            $table->string('transaction_id', 255)->nullable();
            $table->string('redirect_url', 2000)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_id', 255);
            $table->string('type', 120);
            $table->string('checkout_id', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('checkout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('request_hash', 64);
            $table->string('status', 16);
            $table->uuid('checkout_group')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });

        Schema::create('store_payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('use_own')->default(false);
            $table->string('mode', 8)->default('test');
            $table->text('credentials')->nullable();
            $table->string('connection_status', 16)->default('not_connected');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_gateways');
        Schema::dropIfExists('checkout_attempts');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
