<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Advertising Studio (VEN-010c, tenant DB): one row per generated video ad — the
 * wizard choices, the product it advertises (name snapshot kept when the product is
 * removed), live progress, the generated script / caption and the files on the store's
 * public disk (`ai-ads/<id>/…`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_product_id')->nullable()->constrained('store_products')->nullOnDelete();
            $table->string('product_name', 255);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('video_type', 30);
            $table->string('presenter', 20);
            $table->string('voice', 20);
            $table->string('tone', 20);
            $table->string('locale', 10);
            $table->string('platform', 30);
            $table->string('aspect', 10);
            $table->unsignedTinyInteger('duration');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('headline', 255)->nullable();
            $table->string('hook', 500)->nullable();
            $table->json('scenes')->nullable();
            $table->text('voiceover')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('output', 20)->nullable();
            $table->string('video_path', 500)->nullable();
            $table->string('poster_path', 500)->nullable();
            $table->string('audio_path', 500)->nullable();
            $table->json('frames')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ads');
    }
};
