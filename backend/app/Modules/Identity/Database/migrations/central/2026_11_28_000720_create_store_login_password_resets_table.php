<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central store-login password resets (WEB-023 / 024): one hashed token per e-mail;
 * a reset updates the password of every store account (vendor staff) and the owner
 * account that share the e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_login_password_resets', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_login_password_resets');
    }
};
