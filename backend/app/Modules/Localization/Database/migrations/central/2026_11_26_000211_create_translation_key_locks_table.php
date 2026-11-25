<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translation keys an admin locked (ADM-093): AI translation runs skip them, vendors
 * cannot edit locked storefront texts and admins must unlock before editing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_key_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('file', 96);
            $table->string('key', 191);
            $table->foreignId('locked_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['file', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_key_locks');
    }
};
