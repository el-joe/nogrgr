<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin edits of translation keys (ADM-092). Saved here, not to `lang/*` on disk, so a
 * deploy never wipes them; the translator loader merges them over the files.
 * `file` is the editor file id: "storefront" (static text base set), "json",
 * "<group>" (lang/{locale}/<group>.php) or "<module>::<group>".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('file', 96);
            $table->string('key', 191);
            $table->string('locale', 12);
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['file', 'key', 'locale']);
            $table->index(['locale', 'file']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};
