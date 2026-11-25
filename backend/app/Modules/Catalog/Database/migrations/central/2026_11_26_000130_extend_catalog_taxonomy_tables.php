<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin taxonomy screens (ADM-014…017): mega-menu flag and webp / avif thumbnail
 * derivatives on categories, an active flag per attribute option, country targeting of
 * labels, and the auto-fill rule of each badge list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('show_in_menu')->default(true)->after('is_featured');
            $table->string('thumbnail_webp_path')->nullable()->after('thumbnail_path');
            $table->string('thumbnail_avif_path')->nullable()->after('thumbnail_webp_path');
        });

        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('swatch');
        });

        Schema::table('product_labels', function (Blueprint $table): void {
            $table->json('country_ids')->nullable()->after('icon');
        });

        Schema::table('product_badges', function (Blueprint $table): void {
            $table->string('auto_fill', 24)->default('manual')->after('is_active');
            $table->unsignedSmallInteger('auto_fill_limit')->default(24)->after('auto_fill');
        });
    }

    public function down(): void
    {
        Schema::table('product_badges', function (Blueprint $table): void {
            $table->dropColumn(['auto_fill', 'auto_fill_limit']);
        });

        Schema::table('product_labels', function (Blueprint $table): void {
            $table->dropColumn('country_ids');
        });

        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['show_in_menu', 'thumbnail_webp_path', 'thumbnail_avif_path']);
        });
    }
};
