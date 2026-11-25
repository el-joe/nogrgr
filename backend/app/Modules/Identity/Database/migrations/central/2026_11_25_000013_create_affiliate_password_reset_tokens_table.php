<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliates get their own reset-token table: admins and affiliates are separate
 * account types in the central DB and may share an email address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_password_reset_tokens');
    }
};
