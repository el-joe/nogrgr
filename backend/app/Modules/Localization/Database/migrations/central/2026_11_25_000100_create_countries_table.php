<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every ISO 3166-1 country (seeded from Database/Data/countries.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->unique();
            $table->char('numeric_code', 3)->nullable();
            $table->json('name');
            $table->string('phone_code', 8)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('flag', 16);
            $table->string('continent', 16)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
