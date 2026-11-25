<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision #65: approved edit requests now write the store's TENANT override tables
 * (product_overrides / variant_overrides); the central per-tenant copy is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('catalog_product_overrides');
    }

    public function down(): void
    {
        Schema::create('catalog_product_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->string('field', 32);
            $table->string('locale', 8)->default('');
            $table->longText('value')->nullable();
            $table->longText('central_value')->nullable();
            $table->foreignId('edit_request_id')->nullable()->constrained('catalog_edit_requests')->nullOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'variant_key', 'field', 'locale'], 'cpo_unique');
            $table->index('product_id');
        });
    }
};
