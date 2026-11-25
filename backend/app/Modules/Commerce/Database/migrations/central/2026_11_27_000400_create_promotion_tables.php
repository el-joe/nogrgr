<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Central (admin) coupons and flash sales (ADM-025 / 026, decision #65 b). Stores read
| them live through the merge readers; they are never copied into tenant databases.
| Money = USD minor units. No country rows = all countries; scope "selected" = only the
| stores in the *_tenant pivot.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
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
            $table->string('store_scope', 16)->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_country', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['coupon_id', 'country_id']);
        });

        Schema::create('coupon_tenant', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['coupon_id', 'tenant_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_reference', 64);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->timestamp('redeemed_at');
            $table->unique(['coupon_id', 'tenant_id', 'order_reference']);
            $table->index(['coupon_id', 'tenant_id', 'customer_id']);
        });

        Schema::create('flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->decimal('discount_percentage', 5, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true)->index();
            $table->string('banner_path')->nullable();
            $table->string('store_scope', 16)->default('all');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('flash_sale_product', function (Blueprint $table): void {
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('flash_sale_country', function (Blueprint $table): void {
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'country_id']);
        });

        Schema::create('flash_sale_tenant', function (Blueprint $table): void {
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['flash_sale_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_tenant');
        Schema::dropIfExists('flash_sale_country');
        Schema::dropIfExists('flash_sale_product');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_tenant');
        Schema::dropIfExists('coupon_country');
        Schema::dropIfExists('coupons');
    }
};
