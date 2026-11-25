<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery images get a webp derivative next to the avif one (ConvertCatalogMedia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_media', function (Blueprint $table): void {
            $table->string('webp_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_media', function (Blueprint $table): void {
            $table->dropColumn('webp_path');
        });
    }
};
