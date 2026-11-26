<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image search index of CENTRAL product images (P10, central DB): one row per indexed
 * gallery image — the vision description and its embedding vector (JSON list of floats).
 * `source_hash` = sha1(path|size|model): an unchanged image is never re-embedded. Stores
 * never copy these rows (decision #65); their own products live in the tenant table
 * `store_image_embeddings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path', 500);
            $table->char('source_hash', 40);
            $table->text('description');
            $table->longText('embedding');
            $table->unsignedSmallInteger('dims');
            $table->string('model', 120);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_embeddings');
    }
};
