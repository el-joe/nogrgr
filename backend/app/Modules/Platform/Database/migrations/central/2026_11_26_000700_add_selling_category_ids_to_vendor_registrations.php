<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WEB-018a store step "Store categories" (Figma 133:12577): the active top-level
 * catalog categories the vendor sells, kept on the sign-up and copied to
 * tenants.selling_category_ids when the store is provisioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_registrations', function (Blueprint $table): void {
            $table->json('selling_category_ids')->nullable()->after('profit_percent_bps');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_registrations', function (Blueprint $table): void {
            $table->dropColumn('selling_category_ids');
        });
    }
};
