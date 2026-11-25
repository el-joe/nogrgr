<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central lookup: which stores a vendor staff email belongs to (for {central}/tenant/login).
 * Emails are stored as keyed hashes only; kept in sync by VendorStaffDirectoryObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_staff_directory', function (Blueprint $table): void {
            $table->id();
            $table->char('email_hash', 64)->index();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_staff_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'vendor_staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_staff_directory');
    }
};
