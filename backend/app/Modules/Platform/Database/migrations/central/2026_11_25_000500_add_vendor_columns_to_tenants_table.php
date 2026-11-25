<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor profile columns on the central tenants table: owner contact (mirrors the
 * tenant DB owner for central lists/search), store countries, profit % and affiliate
 * commission (integer basis points, 100 bps = 1 %), provisioning tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('owner_name')->nullable()->after('status');
            $table->string('owner_email')->nullable()->index()->after('owner_name');
            $table->string('owner_phone', 32)->nullable()->after('owner_email');
            $table->foreignId('default_country_id')->nullable()->after('owner_phone')->constrained('countries')->nullOnDelete();
            $table->json('selling_country_ids')->nullable()->after('default_country_id');
            $table->unsignedInteger('profit_percent_bps')->default(0)->after('selling_country_ids');
            $table->unsignedInteger('affiliate_commission_bps')->default(0)->after('profit_percent_bps');
            $table->unsignedSmallInteger('provisioning_attempts')->default(0)->after('affiliate_commission_bps');
            $table->text('provisioning_error')->nullable()->after('provisioning_attempts');
            $table->timestamp('provisioned_at')->nullable()->after('provisioning_error');
            $table->timestamp('suspended_at')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_country_id');
            $table->dropColumn([
                'owner_name', 'owner_email', 'owner_phone', 'selling_country_ids', 'profit_percent_bps',
                'affiliate_commission_bps', 'provisioning_attempts', 'provisioning_error', 'provisioned_at', 'suspended_at',
            ]);
        });
    }
};
