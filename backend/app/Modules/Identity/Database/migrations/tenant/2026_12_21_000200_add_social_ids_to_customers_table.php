<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer social sign-in (Google / Apple, P11): the provider's stable user id (`sub`),
 * unique per store. Set on the first social sign-in or when linked to an existing account
 * with the same verified e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('google_id', 191)->nullable()->unique()->after('email');
            $table->string('apple_id', 191)->nullable()->unique()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['apple_id']);
            $table->dropColumn(['google_id', 'apple_id']);
        });
    }
};
