<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One-time sign-in tokens (AFF-007): the vendor panel's "Open affiliate dashboard" issues
| one for the store's vendor affiliate; {central}/affiliate/sso/{token} consumes it.
| Only the SHA-256 of the token is stored; a token works once and for a short time.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_sso_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['affiliate_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_sso_tokens');
    }
};
