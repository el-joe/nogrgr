<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The store's own copies of the shopper e-mail templates (VEN-080, tenant DB): synced
| from the central `email_templates` ("Sync to tenants" → EmailTemplatesSynced) unless
| the store customised them (is_customized). Subject / body per locale (JSON).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 80)->unique();
            $table->json('subject');
            $table->json('body');
            $table->string('status', 20)->default('active');
            $table->boolean('is_customized')->default(false)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_email_templates');
    }
};
