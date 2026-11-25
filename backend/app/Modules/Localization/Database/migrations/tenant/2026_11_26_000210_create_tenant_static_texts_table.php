<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The store's storefront static texts per locale (decision #45), copied from the
 * platform base set at provisioning and kept in step by the sync job. `source_hash`
 * is the hash of the base value the row was made from; `source` base | ai | manual;
 * `is_locked` keeps a value from sync and AI overwrites (set on vendor edits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_static_texts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191);
            $table->string('locale', 12);
            $table->text('value');
            $table->char('source_hash', 40)->nullable();
            $table->string('source', 16)->default('base');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['key', 'locale']);
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_static_texts');
    }
};
