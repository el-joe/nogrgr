<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central e-mail templates (Admin › Settings › Email templates): one per
 * EmailTemplateEvent, subject + sanitised HTML body per locale (JSON), status and the
 * last "sync to tenants". Tenants copy them later (EmailTemplatesSynced event).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 80)->unique();
            $table->string('audience', 20)->index();
            $table->json('subject');
            $table->json('body');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
