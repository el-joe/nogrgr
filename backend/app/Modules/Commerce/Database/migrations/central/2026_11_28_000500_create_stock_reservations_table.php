<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Stock holds of storefront orders (decision #68). Central so that holds on a central
| product are visible to every store selling it; own-product holds are scoped to their
| tenant. product_id = central product id (source central) or store_products id (own).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('checkout_group')->index();
            $table->uuid('order_uuid');
            $table->string('product_source', 16);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->unsignedInteger('quantity');
            $table->string('status', 16);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['product_source', 'product_id', 'variant_key', 'status'], 'stock_reservations_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
