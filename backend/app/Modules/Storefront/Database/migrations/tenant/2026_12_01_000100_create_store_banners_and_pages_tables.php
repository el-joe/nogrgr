<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Store-owned banners (VEN-035, decision #65 b) + the central banners the store hides,
| and the store CMS pages (SF-ECO-034 / VEN-033). Country ids are central ids (no FK).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 32)->default('home.hero')->index();
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('image_path');
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['placement', 'sort_order']);
        });

        Schema::create('store_banner_countries', function (Blueprint $table): void {
            $table->foreignId('store_banner_id')->constrained('store_banners')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id');
            $table->primary(['store_banner_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('hidden_central_banners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('banner_id')->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('store_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('type', 16)->default('custom')->index();
            $table->json('title');
            $table->json('body')->nullable();
            $table->json('meta_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pages');
        Schema::dropIfExists('hidden_central_banners');
        Schema::dropIfExists('store_banner_countries');
        Schema::dropIfExists('store_banners');
    }
};
