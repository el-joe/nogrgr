<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store members' notification switches (VEN-087): one row per staff × topic × channel
 * (VendorNotificationTopic × NotificationChannel). A missing row means the topic default.
 * Lives in the store database because staff ids repeat across stores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_staff_id')->constrained('vendor_staff')->cascadeOnDelete();
            $table->string('topic', 32);
            $table->string('channel', 16);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['vendor_staff_id', 'topic', 'channel'], 'notification_preferences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
