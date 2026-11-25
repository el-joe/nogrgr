<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central branches (warehouses / pickup points), the countries each branch is the default
 * fulfilment branch for (one branch per country), the cities (zones) it serves, and the
 * stock of every central product / variant per branch (variant_key 0 = product without variants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('code', 32)->unique();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('address', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('pickup_hours', 120)->nullable();
            $table->boolean('allows_pickup')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'is_active']);
        });

        Schema::create('branch_default_countries', function (Blueprint $table): void {
            $table->foreignId('country_id')->primary()->constrained('countries')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
        });

        Schema::create('branch_zones', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->primary(['branch_id', 'city_id']);
        });

        Schema::create('branch_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_key')->default(0)->comment('product_variant_id, 0 = no variant');
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'variant_key'], 'branch_stock_unique');
            $table->index(['product_id', 'variant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stock');
        Schema::dropIfExists('branch_zones');
        Schema::dropIfExists('branch_default_countries');
        Schema::dropIfExists('branches');
    }
};
