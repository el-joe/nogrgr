<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform configuration store (central): one row per `group` + `key` (mail.host,
 * ai.openai.api_key, general.platform_name…). `value` is JSON; secret rows are
 * encrypted with the app key (`is_secret`). Read through Integrations'
 * SettingsRepository and overlaid on config() at boot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40);
            $table->string('key', 80);
            $table->longText('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
