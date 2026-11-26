<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Central (admin) default banners, ADM-085 (decision #65 b): per placement and country,
| sorted, translatable texts, all / selected stores, optional schedule. Stores read them
| merged with their own `store_banners` through Storefront\Services\Banners\BannerCatalog.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
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
            $table->string('store_scope', 16)->default('all');
            $table->timestamps();
            $table->index(['placement', 'sort_order']);
        });

        Schema::create('banner_country', function (Blueprint $table): void {
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['banner_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('banner_tenant', function (Blueprint $table): void {
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['banner_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_tenant');
        Schema::dropIfExists('banner_country');
        Schema::dropIfExists('banners');
    }
};
