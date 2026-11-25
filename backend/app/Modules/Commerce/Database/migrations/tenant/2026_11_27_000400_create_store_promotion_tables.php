<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Store-owned (vendor) coupons and flash sales (VEN-040 / 041, decision #65 b) and the
| list of central promotions the store hides. Central rows are never copied here: the
| merge readers combine both sides and tag `source`. Country ids are central ids; flash
| sale products are a typed pair (central product id | own store_products id).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120)->nullable();
            $table->string('type', 20);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->unsignedBigInteger('max_discount_minor')->nullable();
            $table->unsignedBigInteger('min_spend_minor')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('store_coupon_countries', function (Blueprint $table): void {
            $table->foreignId('store_coupon_id')->constrained('store_coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id');
            $table->primary(['store_coupon_id', 'country_id']);
        });

        Schema::create('store_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_coupon_id')->constrained('store_coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_reference', 64);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->timestamp('redeemed_at');
            $table->unique(['store_coupon_id', 'order_reference']);
            $table->index(['store_coupon_id', 'customer_id']);
        });

        Schema::create('store_flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->decimal('discount_percentage', 5, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true)->index();
            $table->string('banner_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('store_flash_sale_products', function (Blueprint $table): void {
            $table->foreignId('store_flash_sale_id')->constrained('store_flash_sales')->cascadeOnDelete();
            $table->string('product_source', 16);
            $table->unsignedBigInteger('product_id');
            $table->primary(['store_flash_sale_id', 'product_source', 'product_id']);
            $table->index(['product_source', 'product_id']);
        });

        Schema::create('store_flash_sale_countries', function (Blueprint $table): void {
            $table->foreignId('store_flash_sale_id')->constrained('store_flash_sales')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id');
            $table->primary(['store_flash_sale_id', 'country_id']);
        });

        Schema::create('hidden_central_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('central_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['kind', 'central_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_central_promotions');
        Schema::dropIfExists('store_flash_sale_countries');
        Schema::dropIfExists('store_flash_sale_products');
        Schema::dropIfExists('store_flash_sales');
        Schema::dropIfExists('store_coupon_redemptions');
        Schema::dropIfExists('store_coupon_countries');
        Schema::dropIfExists('store_coupons');
    }
};
