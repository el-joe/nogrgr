<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central catalog categories (tree) and the attributes (old "variations") with their
 * options. Translatable fields are spatie/laravel-translatable JSON {en, ar, …}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug', 191)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('thumbnail_path')->nullable();
            $table->string('external_source', 32)->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['status', 'is_featured']);
            $table->unique(['external_source', 'external_id']);
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('display_type', 16)->default('text');
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('external_source', 32)->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['external_source', 'external_id']);
        });

        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->json('name');
            $table->string('swatch', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('external_source', 32)->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attribute_id', 'sort_order']);
            $table->unique(['external_source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
    }
};
