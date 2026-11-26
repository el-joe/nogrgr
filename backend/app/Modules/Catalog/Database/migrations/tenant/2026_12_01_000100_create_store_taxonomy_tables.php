<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store categories and badges on top of the central taxonomy (decision #74, VEN-017/018).
 * Central rows are NEVER copied (#65): the store only keeps its own data —
 *  - `store_categories`: categories the store creates itself (own content, image, countries);
 *  - `store_central_categories`: per central category the store's visibility + position
 *    (no row = visible, after the positioned ones);
 *  - `store_category_positions`: product order inside a category ("central:<id>" / "store:<id>");
 *  - `store_badge_positions`: product order inside a central badge;
 *  - `store_products.store_category_ids`: placement of a product in store categories
 *    (central placement stays in `category_ids`, null = the central categories).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('country_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('store_central_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('central_category_id')->unique();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('store_category_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('category_ref', 40);
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_ref', 'store_product_id']);
        });

        Schema::create('store_badge_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('badge_id');
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['badge_id', 'store_product_id']);
        });

        Schema::table('store_products', function (Blueprint $table): void {
            $table->json('store_category_ids')->nullable()->after('category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table): void {
            $table->dropColumn('store_category_ids');
        });

        Schema::dropIfExists('store_badge_positions');
        Schema::dropIfExists('store_category_positions');
        Schema::dropIfExists('store_central_categories');
        Schema::dropIfExists('store_categories');
    }
};
