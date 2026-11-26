<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image search index of the store's OWN products (P10, tenant DB): same shape as the
 * central `product_image_embeddings`, keyed by `store_products.id` (own rows only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_image_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
            $table->string('path', 500);
            $table->char('source_hash', 40);
            $table->text('description');
            $table->longText('embedding');
            $table->unsignedSmallInteger('dims');
            $table->string('model', 120);
            $table->timestamps();

            $table->index('store_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_image_embeddings');
    }
};
