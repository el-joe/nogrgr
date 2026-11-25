<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront account: notification preferences (JSON toggles per type and channel)
 * and soft delete for "delete my account" (the row is anonymised, orders keep it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone_country', 2)->nullable()->after('phone');
            $table->json('notification_preferences')->nullable()->after('country_code');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone_country', 'notification_preferences']);
        });
    }
};
