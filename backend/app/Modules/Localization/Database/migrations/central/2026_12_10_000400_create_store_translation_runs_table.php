<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid whole-store AI translation runs (decisions #44 / #45): one row per bought
 * language, created when its purchase is paid. The P10 Ai run moves it through
 * queued → translating (progress) → completed | failed; "retry" puts a failed run back
 * in the queue. Central so the admin purchases pages can show it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_translation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->string('language_code', 12);
            $table->unsignedBigInteger('addon_purchase_id')->nullable()->unique();
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('done_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'language_code']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_translation_runs');
    }
};
