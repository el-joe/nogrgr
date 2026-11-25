<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin › My profile (ADM-005): avatar image and display timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('timezone', 64)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn(['avatar_path', 'timezone']);
        });
    }
};
