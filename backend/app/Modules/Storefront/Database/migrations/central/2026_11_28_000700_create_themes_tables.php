<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central theme catalogue (13 storefront themes) and the gallery categories used by
 * the central website templates pages (WEB-002 / 003). P7 extends it (activation per
 * country, home sections, admin edit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('family', 32)->index();
            $table->unsignedTinyInteger('variant')->default(1);
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('preview_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('theme_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->json('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('theme_category_theme', function (Blueprint $table): void {
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('theme_category_id')->constrained('theme_categories')->cascadeOnDelete();
            $table->primary(['theme_id', 'theme_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_category_theme');
        Schema::dropIfExists('theme_categories');
        Schema::dropIfExists('themes');
    }
};
