<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISO 4217 currencies (seeded from Database/Data/currencies.json). USD is the base
 * currency (rate 1); `rate` = units of this currency per 1 USD, 6 decimals, synced
 * daily from open.er-api.com. Display only: every stored amount is USD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 3)->unique();
            $table->json('name');
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->decimal('rate', 20, 6)->default(1);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_base')->default(false);
            $table->timestamp('rate_synced_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
