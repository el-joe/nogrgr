<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests center (ADM-018, central DB): vendor → platform requests.
 *
 *  - product / manufacturing / brand requests share one conversation model:
 *    catalog_request_messages (+ attachments on the private disk) and, for the paid kinds,
 *    catalog_request_payments (each one backed by a Billing invoice).
 *    `admin_seen_at` = the admin opened the request since it was submitted / last updated
 *    by the vendor; unread = vendor messages with read_at NULL (and vice versa).
 *  - product edit requests carry a field diff (`changes`); approval writes
 *    catalog_product_overrides rows for that tenant only (the central product stays unchanged).
 *  - tenant change requests (selling countries / categories) store the full requested and
 *    current id lists; approval writes the requested list to the tenant.
 * Money columns are USD integer minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_product_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('product_url', 2000)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->bigInteger('target_price')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('priority', 16)->default('normal');
            $table->string('submitted_by')->nullable();
            $table->timestamp('admin_seen_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['archived_at', 'status']);
        });

        Schema::create('catalog_manufacturing_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('reference_url', 2000)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 24)->default('requested');
            $table->text('admin_notes')->nullable();
            $table->string('submitted_by')->nullable();
            $table->timestamp('admin_seen_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });

        Schema::create('catalog_brand_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('brand_name');
            $table->text('description')->nullable();
            $table->string('website', 2000)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('submitted_by')->nullable();
            $table->timestamp('admin_seen_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });

        Schema::create('catalog_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('requestable_type', 64);
            $table->unsignedBigInteger('requestable_id');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('sender', 16);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('sender_name');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['requestable_type', 'requestable_id', 'id'], 'crm_thread_index');
            $table->index(['requestable_type', 'sender', 'read_at'], 'crm_unread_index');
        });

        Schema::create('catalog_request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('catalog_request_messages')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('catalog_request_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('requestable_type', 64);
            $table->unsignedBigInteger('requestable_id');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('label');
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 16)->default('pending');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['requestable_type', 'requestable_id'], 'crp_request_index');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('catalog_edit_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->json('changes');
            $table->json('files')->nullable();
            $table->text('vendor_note')->nullable();
            $table->string('submitted_by')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'created_at']);
        });

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

        Schema::create('catalog_tenant_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 16);
            $table->json('requested_ids');
            $table->json('current_ids');
            $table->text('vendor_note')->nullable();
            $table->string('submitted_by')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['status', 'created_at']);
        });

        if (! Schema::hasColumn('tenants', 'selling_category_ids')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->json('selling_category_ids')->nullable()->after('selling_country_ids');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'selling_category_ids')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropColumn('selling_category_ids');
            });
        }

        Schema::dropIfExists('catalog_tenant_change_requests');
        Schema::dropIfExists('catalog_product_overrides');
        Schema::dropIfExists('catalog_edit_requests');
        Schema::dropIfExists('catalog_request_payments');
        Schema::dropIfExists('catalog_request_attachments');
        Schema::dropIfExists('catalog_request_messages');
        Schema::dropIfExists('catalog_brand_requests');
        Schema::dropIfExists('catalog_manufacturing_requests');
        Schema::dropIfExists('catalog_product_requests');
    }
};
