<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store catalog (tenant DB, decision #65 sub-note). Central product data is NEVER copied:
 *  - `store_products`: one row per product the store sells. source = central → only the
 *    central `products.id` + store business data the vendor edits directly (active,
 *    featured, sort, `markup` per country, `variant_markup` per central variant × country,
 *    store placement `category_ids` / `badge_ids`, null = central ones); source = own → a
 *    vendor-own product whose content lives in the own-only columns (null for central rows).
 *  - `product_overrides` / `variant_overrides`: central content the store overrides
 *    (nullable per field, keyed by the CENTRAL ids so they survive removal / re-import).
 *    Written ONLY by approved edit requests (ADM-018b); the vendor never writes them
 *    directly. Read = per field: override when set, else central (StoreCatalogReader).
 *    Central stock is never overridden (it comes from central branches).
 *  - `store_product_variants`, `catalog_media`: own products only.
 * Markup rows: {"default"|country_id: {profit_type, profit_value}}. Money = USD minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16)->index();
            $table->unsignedBigInteger('central_product_id')->nullable()->unique();
            $table->json('name')->nullable();
            $table->json('label')->nullable();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('sku', 64)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('markup')->nullable();
            $table->json('variant_markup')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('badge_ids')->nullable();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('sale_price')->nullable();
            $table->bigInteger('cost_price')->nullable();
            $table->integer('stock')->nullable();
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('is_taxable')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('country_ids')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('store_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->json('title')->nullable();
            $table->string('sku', 64)->nullable();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('sale_price')->nullable();
            $table->integer('stock')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->json('name')->nullable();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('approvals')->nullable();
            $table->timestamps();
        });

        Schema::create('variant_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->unique();
            $table->json('title')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('approvals')->nullable();
            $table->timestamps();
        });

        Schema::create('store_product_markets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id');
            $table->json('placements')->nullable();
            $table->timestamps();

            $table->unique(['store_product_id', 'country_id']);
            $table->index('country_id');
        });

        // Gallery of own products (same shape as the central table; model CatalogMedia).
        Schema::create('catalog_media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('mediable');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('webp_path')->nullable();
            $table->string('avif_path')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('conversion_status', 16)->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_price_list_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 16);
            $table->json('product_ids');
            $table->string('profit_type', 16);
            $table->decimal('profit_value', 12, 2);
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->string('error')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_price_list_runs');
        Schema::dropIfExists('catalog_media');
        Schema::dropIfExists('store_product_markets');
        Schema::dropIfExists('variant_overrides');
        Schema::dropIfExists('product_overrides');
        Schema::dropIfExists('store_product_variants');
        Schema::dropIfExists('store_products');
    }
};
