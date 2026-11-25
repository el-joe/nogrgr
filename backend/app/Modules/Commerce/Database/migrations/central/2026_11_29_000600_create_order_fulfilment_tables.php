<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Platform-side order records (P5 batch 3, decision #69), central, keyed by tenant_id +
| the store order uuid:
|   order_settlements  — "Pay platform" payments of a vendor-collected order (formulas §3:
|                        vendor_own_platform = platform_profit − Σ paid settlements);
|   order_attachments  — shipping label / packing proof / customs files on the private
|                        `central` disk (admin fulfilment);
|   order_admin_notes  — internal notes, visible to admins only.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_settlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('order_uuid');
            $table->string('order_number', 32);
            $table->bigInteger('amount');
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('product_cost')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('gateway', 32);
            $table->unsignedBigInteger('payment_log_id')->nullable()->index();
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('paid_by_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_uuid']);
        });

        Schema::create('order_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('order_uuid');
            $table->string('kind', 24);
            $table->string('disk', 32)->default('central');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->string('uploaded_by_name', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_uuid']);
        });

        Schema::create('order_admin_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('order_uuid');
            $table->text('note');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_admin_notes');
        Schema::dropIfExists('order_attachments');
        Schema::dropIfExists('order_settlements');
    }
};
