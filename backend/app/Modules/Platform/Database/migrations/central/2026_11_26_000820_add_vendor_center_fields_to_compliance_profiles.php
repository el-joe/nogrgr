<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor compliance center (VEN-084): identity (owner), company and contact fields of the
 * store KYC profile. The owner ID number is encrypted at rest (model cast); a profile the
 * vendor has not submitted yet is a `draft` (never in the admin queue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_profiles', function (Blueprint $table): void {
            $table->string('account_type', 16)->nullable()->after('risk');
            $table->string('owner_name')->nullable()->after('account_type');
            $table->text('owner_id_number')->nullable()->after('owner_name');
            $table->date('owner_id_expires_at')->nullable()->after('owner_id_number');
            $table->date('owner_birth_date')->nullable()->after('owner_id_expires_at');
            $table->string('owner_phone', 32)->nullable()->after('owner_birth_date');
            $table->string('registration_number', 100)->nullable()->after('legal_name');
            $table->date('registration_expires_at')->nullable()->after('registration_number');
            $table->string('vat_number', 100)->nullable()->after('registration_expires_at');
            $table->foreignId('country_id')->nullable()->after('vat_number')->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
            $table->string('address', 500)->nullable()->after('city_id');
            $table->string('company_phone', 32)->nullable()->after('address');
            $table->string('company_email')->nullable()->after('company_phone');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn([
                'account_type', 'owner_name', 'owner_id_number', 'owner_id_expires_at', 'owner_birth_date', 'owner_phone',
                'registration_number', 'registration_expires_at', 'vat_number', 'address', 'company_phone', 'company_email',
            ]);
        });
    }
};
