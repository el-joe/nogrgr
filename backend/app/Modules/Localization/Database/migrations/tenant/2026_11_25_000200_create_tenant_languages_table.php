<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Languages a store has switched on (codes of central `languages`). ar + en are
 * added at provisioning (source "default") and cannot be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('language_code', 12)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 16)->default('free');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_languages');
    }
};
