<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved customer addresses (docs/plan/06-storefront-account-spec.md §4). `country_id`
 * references the central `countries` table (other database: indexed, no FK); the
 * country name is never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('full_name', 120);
            $table->string('email', 120)->nullable();
            $table->string('phone', 20);
            $table->char('phone_country', 2);
            $table->string('address_line_1', 300);
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->unsignedBigInteger('country_id')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
