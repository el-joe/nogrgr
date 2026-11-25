<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link stores and sign-ups to their owner (Identity › Owners, one owner many stores).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('slug')->constrained('owners')->nullOnDelete();
        });

        Schema::table('vendor_registrations', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
