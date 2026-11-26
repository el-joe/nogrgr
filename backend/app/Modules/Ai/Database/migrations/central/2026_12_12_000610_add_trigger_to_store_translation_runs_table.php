<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly delta auto-translation (decision #44 "source-hash delta"): delta runs are
 * store translation runs with trigger = delta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_translation_runs', function (Blueprint $table): void {
            $table->string('trigger', 16)->default('purchase')->after('addon_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_translation_runs', function (Blueprint $table): void {
            $table->dropColumn('trigger');
        });
    }
};
