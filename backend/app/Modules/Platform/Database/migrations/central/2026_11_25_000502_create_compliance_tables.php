<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store KYC: one profile per tenant + its documents (files on the private disk).
 * Identity numbers and IBAN are encrypted at rest (model casts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 32)->default('needs_review')->index();
            $table->string('risk', 16)->default('low');
            $table->string('legal_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('iban')->nullable();
            $table->string('account_name')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_ip', 45)->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('compliance_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('compliance_profile_id')->constrained('compliance_profiles')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 16)->default('review');
            $table->text('document_number')->nullable();
            $table->string('holder_name')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('matches_owner')->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['compliance_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
        Schema::dropIfExists('compliance_profiles');
    }
};
