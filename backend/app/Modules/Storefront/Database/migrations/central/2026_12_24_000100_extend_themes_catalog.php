<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Theme catalogue for admin (ADM-086 / 087 / 088, decision #119 / #120): every one of the
| 13 themes gets its own platform default flag, allowed countries (theme_country, empty =
| all countries), home section defaults (ordered [{key, enabled}], null = the theme's
| section library order, all on) and its template parts (theme_parts).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_active')->index();
            $table->json('home_sections')->nullable()->after('preview_path');
        });

        Schema::create('theme_country', function (Blueprint $table): void {
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['theme_id', 'country_id']);
        });

        Schema::create('theme_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name', 120);
            $table->json('settings')->nullable();
            $table->string('preview_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['theme_id', 'type', 'is_default']);
        });

        // Existing catalogues: Ecommet becomes the platform default.
        if (! DB::table('themes')->where('is_default', true)->exists()) {
            DB::table('themes')->where('slug', 'ecommet')->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_parts');
        Schema::dropIfExists('theme_country');

        Schema::table('themes', function (Blueprint $table): void {
            $table->dropIndex(['is_default']);
            $table->dropColumn(['is_default', 'home_sections']);
        });
    }
};
