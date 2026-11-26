<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item state of the paid full-store translation runs (decision #44, tenant DB):
 * one row per section + item + locale. `done` rows with an unchanged `source_hash` are
 * skipped on resume / retry ("retry failed only"); a changed hash re-translates the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_translation_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('run_id')->nullable()->index();
            $table->string('section', 32);
            $table->string('item_key', 191);
            $table->string('locale', 12);
            $table->string('status', 12);
            $table->char('source_hash', 40)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();

            $table->unique(['section', 'item_key', 'locale'], 'store_translation_items_unique');
            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_translation_items');
    }
};
