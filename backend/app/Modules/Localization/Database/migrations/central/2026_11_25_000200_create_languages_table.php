<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every ISO 639-1 language (seeded from Database/Data/languages.json). Prices are
 * USD minor units; `country_ids` null = offered in every country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->json('name');
            $table->string('native_name', 100);
            $table->string('direction', 3)->default('ltr');
            $table->string('flag', 16)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('price_minor')->default(0);
            $table->json('country_ids')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
