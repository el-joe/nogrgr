<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central products and variants. Money columns are USD integer minor units.
 *
 *  - tenant cost of a product = sale_price when > 0, else base_price (old TenantPricingService);
 *    a variant's cost = its own `price` when set, else the product cost.
 *  - `fixed_shipping_costs` JSON {country_id: minor} = weight_grams × price_per_gram, precomputed
 *    by Shipping's RecomputeFixedShippingCosts job (rates or weight changed).
 *  - `allowed_country_ids`: NULL = everywhere, [] = nowhere, [ids] = only there (ORDER_FORMULAS).
 *  - `assignment_scope`: all tenants, or only the tenants in product_tenant_assignments
 *    ("tenant-owned" exclusivity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('sku', 191)->unique();
            $table->json('name');
            $table->json('label')->nullable();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('delivery_scope', 24)->default('all_zones');
            $table->string('assignment_scope', 16)->default('all');
            $table->bigInteger('base_price')->default(0);
            $table->bigInteger('sale_price')->nullable();
            $table->bigInteger('cost_price')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('requires_shipping')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('fixed_shipping_costs')->nullable();
            $table->json('allowed_country_ids')->nullable();
            $table->string('factory')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('out_of_stock_at')->nullable();
            $table->string('external_source', 32)->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
            $table->index('assignment_scope');
            $table->unique(['external_source', 'external_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 191)->nullable()->unique();
            $table->json('title')->nullable();
            $table->bigInteger('price')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('fixed_shipping_costs')->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 16)->default('active');
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'position']);
        });

        Schema::create('product_attribute', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_id']);
        });

        Schema::create('product_variant_option', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_option_id')->constrained('attribute_options')->cascadeOnDelete();
            $table->primary(['product_variant_id', 'attribute_option_id']);
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['category_id', 'product_id']);
            $table->index(['product_id']);
        });

        Schema::create('product_tenant_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tenant_assignments');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('product_variant_option');
        Schema::dropIfExists('product_attribute');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
