<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-category, per-channel notification switches of central users.
 * A missing row means the category default (NotificationCategory::defaultEnabled()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->morphs('notifiable');
            $table->string('category', 32);
            $table->string('channel', 16);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'category', 'channel'], 'notification_preferences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
