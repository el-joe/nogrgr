<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Old system (multi-vendor / dokan_v2) id → new id, per store entity (tenant DB) (P12 data migration,
 * `php artisan legacy:migrate`). Re-runs look rows up here and update instead of duplicating.
 * The central twin is `legacy_central_ids`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_store_ids', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 48);
            $table->string('legacy_key', 64);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['entity', 'legacy_key']);
            $table->index(['entity', 'new_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_store_ids');
    }
};
