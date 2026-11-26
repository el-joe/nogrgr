<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage of each paid store translation run (decision #44 "usage/token logs"): the
 * provider + model that ran it and the tokens they reported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_translation_runs', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable()->after('last_error');
            $table->string('model', 120)->nullable()->after('provider');
            $table->unsignedBigInteger('prompt_tokens')->default(0)->after('model');
            $table->unsignedBigInteger('completion_tokens')->default(0)->after('prompt_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('store_translation_runs', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'model', 'prompt_tokens', 'completion_tokens']);
        });
    }
};
