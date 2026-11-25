<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External catalog import (ADM-013): runs with live counters, their per-item log, the
 * external id => local record links that make re-runs idempotent, and the source
 * category => central category mapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 32);
            $table->string('status', 16)->default('queued');
            $table->json('options')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->unsignedInteger('pages_done')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('images_total')->default(0);
            $table->unsignedInteger('images_done')->default(0);
            $table->string('batch_id', 64)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['driver', 'status']);
            $table->index('created_at');
        });

        Schema::create('catalog_import_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_import_runs')->cascadeOnDelete();
            $table->string('result', 16);
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('item_name', 191)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('message', 500);
            $table->timestamp('created_at')->nullable();

            $table->index(['run_id', 'result']);
        });

        Schema::create('catalog_import_links', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 32);
            $table->string('type', 16);
            $table->unsignedBigInteger('external_id');
            $table->unsignedBigInteger('local_id');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->unique(['driver', 'type', 'external_id']);
            $table->index(['type', 'local_id']);
        });

        Schema::create('catalog_import_category_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 32);
            $table->unsignedBigInteger('external_id');
            $table->json('name');
            $table->unsignedBigInteger('parent_external_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['driver', 'external_id']);
            $table->index(['driver', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_category_maps');
        Schema::dropIfExists('catalog_import_links');
        Schema::dropIfExists('catalog_import_logs');
        Schema::dropIfExists('catalog_import_runs');
    }
};
