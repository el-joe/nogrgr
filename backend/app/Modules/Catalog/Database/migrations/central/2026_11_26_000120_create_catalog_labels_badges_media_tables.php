<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Labels (icon + translatable text with placeholders such as {delivery_date}), badges
 * (one row per BadgeType, products assigned and ordered per badge, optionally per
 * country) and the product gallery (catalog_media).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_labels', function (Blueprint $table): void {
            $table->id();
            $table->json('text');
            $table->string('icon', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_label_product', function (Blueprint $table): void {
            $table->foreignId('product_label_id')->constrained('product_labels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['product_label_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('product_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32)->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_badge_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_badge_id')->constrained('product_badges')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id')->default(0)->comment('0 = every country');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_badge_id', 'product_id', 'country_id'], 'pbp_unique');
            $table->index(['product_badge_id', 'country_id', 'sort_order'], 'pbp_sort_index');
        });

        Schema::create('catalog_media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('mediable');
            $table->string('disk', 32);
            $table->string('path');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_media');
        Schema::dropIfExists('product_badge_product');
        Schema::dropIfExists('product_badges');
        Schema::dropIfExists('product_label_product');
        Schema::dropIfExists('product_labels');
    }
};
