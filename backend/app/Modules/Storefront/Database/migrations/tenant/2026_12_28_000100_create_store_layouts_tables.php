<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Page builder (VEN-032, decision #117 b, tenant DB): one layout per slot — "home" (store
| default), "home:{country id}" (a selling country with its own theme) or "page:{store page
| id}" — with the working draft and the published sections (JSON), and every publish kept
| as a numbered version (restore = copy a version into the draft).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('slot', 40)->unique();
            $table->string('scope', 16)->index();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('page_id')->nullable()->index();
            $table->unsignedBigInteger('theme_id')->nullable();
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('draft_updated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_layout_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_layout_id')->constrained('store_layouts')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedBigInteger('theme_id')->nullable();
            $table->json('sections');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->string('published_by_name', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['store_layout_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_layout_versions');
        Schema::dropIfExists('store_layouts');
    }
};
