<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product gallery videos (decision #114): `type` image / video (MediaType) and the
 * optional poster frame written by GenerateVideoPoster (ffmpeg).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_media', function (Blueprint $table): void {
            $table->string('type', 16)->default('image')->after('mediable_id');
            $table->string('poster_path')->nullable()->after('avif_path');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_media', function (Blueprint $table): void {
            $table->dropColumn(['type', 'poster_path']);
        });
    }
};
