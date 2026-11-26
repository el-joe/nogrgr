<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store content fields the vendor translated by hand (VEN-075, decision #44 "manual
 * edits are locked and never overwritten"): the paid AI run skips a field + locale
 * listed here. `content_type` is the StoreContentType value (category, product, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translation_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 32);
            $table->unsignedBigInteger('content_id');
            $table->string('field', 64);
            $table->string('locale', 12);
            $table->timestamps();

            $table->unique(['content_type', 'content_id', 'field', 'locale'], 'content_translation_locks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translation_locks');
    }
};
