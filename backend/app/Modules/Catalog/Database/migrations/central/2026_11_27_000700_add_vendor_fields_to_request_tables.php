<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor request forms (VEN-050b / 050h): product requests carry the target markets,
 * brand requests a category and countries. Country ids are central `countries.id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_product_requests', function (Blueprint $table): void {
            $table->json('target_country_ids')->nullable()->after('target_price');
        });

        Schema::table('catalog_brand_requests', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('website')->constrained('categories')->nullOnDelete();
            $table->json('target_country_ids')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_brand_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('target_country_ids');
        });

        Schema::table('catalog_product_requests', function (Blueprint $table): void {
            $table->dropColumn('target_country_ids');
        });
    }
};
