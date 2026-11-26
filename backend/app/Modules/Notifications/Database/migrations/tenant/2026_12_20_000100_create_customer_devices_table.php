<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app installs of the store (P11): one row per app install (`device_id`, generated
 * by the app), its FCM registration token and the signed-in customer (null = guest).
 * Push sending (Notifications\Services\CustomerPushService) reads it; invalid tokens are
 * cleared when FCM rejects them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('device_id', 100)->unique();
            $table->string('platform', 16);
            $table->string('push_token', 512)->nullable();
            $table->boolean('push_enabled')->default(true);
            $table->string('app_version', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('locale', 12)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'push_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};
