<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin AI translation runs (P10, ADM-091b / ADM-092a): fill the missing texts of one
 * language in the central catalogue (kind "catalog") or in the static key files (kind
 * "static_keys") with the custom provider (decision #44 a). Per-section counters live in
 * `sections` ({section: {total, done, failed}}); a retry re-scans what is still missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_translation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('locale', 12);
            $table->json('scope')->nullable();
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('done_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->json('sections')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->string('provider', 32)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'locale']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_translation_runs');
    }
};
