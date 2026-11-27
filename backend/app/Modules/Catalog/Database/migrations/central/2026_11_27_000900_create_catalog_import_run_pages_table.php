<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source pages an import run has finished (#112, client feedback): every page runs as a
 * queued job in any order, so a resumed run re-queues exactly the pages not listed here
 * and a duplicate page job never counts twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_import_run_pages', function (Blueprint $table): void {
            $table->foreignId('run_id')->constrained('catalog_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->timestamp('created_at')->nullable();

            $table->primary(['run_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_run_pages');
    }
};
