<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| After-sales (P5 batch 3, decision #69): returns and refunds of store orders. Central
| tables keyed by tenant_id (+ order uuid / number) so the admin lists every store in one
| query and the vendor panel reads its own rows through the tenant scope. Money = USD
| minor units. The platform return policy is one row + its reasons.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_policies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('window_days')->default(14);
            $table->unsignedInteger('restocking_fee_bps')->default(0);
            $table->string('return_shipping_payer', 32)->default('customer_unless_fault');
            $table->json('non_returnable_category_ids')->nullable();
            $table->boolean('require_video')->default(true);
            $table->boolean('allow_exchanges')->default(true);
            $table->timestamps();
        });

        Schema::create('return_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->boolean('requires_video')->default(false);
            $table->boolean('seller_fault')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('order_uuid');
            $table->string('order_number', 32);
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('store_product_id')->nullable();
            $table->string('product_name', 255);
            $table->string('variant_label', 255)->nullable();
            $table->unsignedInteger('quantity');
            $table->string('type', 16)->index();
            $table->string('reason_code', 40);
            $table->boolean('seller_fault')->default(false);
            $table->text('description');
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 120)->nullable();
            $table->bigInteger('items_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('return_fee')->default(0);
            $table->bigInteger('refund_amount')->default(0);
            $table->string('inspection_result', 24)->nullable();
            $table->string('inspection_condition', 120)->nullable();
            $table->text('inspection_notes')->nullable();
            $table->boolean('restocked')->default(false);
            $table->unsignedBigInteger('restock_branch_id')->nullable();
            $table->string('exchange_tracking_number', 64)->nullable();
            $table->string('created_by_type', 16);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('exchange_shipped_at')->nullable();
            $table->timestamp('exchanged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_uuid']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('return_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 120)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('return_request_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('disk', 32)->default('central');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 16);
            $table->string('uploaded_by_type', 16);
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('order_uuid');
            $table->string('order_number', 32);
            $table->foreignId('return_request_id')->nullable()->constrained('return_requests')->nullOnDelete();
            $table->string('source', 16)->index();
            $table->string('reason', 500);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('items_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('return_fee')->default(0);
            $table->bigInteger('amount');
            $table->string('method', 24);
            $table->string('gateway', 32)->nullable();
            $table->string('payment_source', 16)->nullable();
            $table->string('original_transaction_id', 255)->nullable();
            $table->string('gateway_refund_id', 255)->nullable();
            $table->string('status', 16)->index();
            $table->string('failure_reason', 500)->nullable();
            $table->boolean('restock')->default(false);
            $table->string('requested_by_type', 16);
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->string('processed_by_type', 16)->nullable();
            $table->unsignedBigInteger('processed_by_id')->nullable();
            $table->string('processed_by_name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('requested_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_uuid']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('refund_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->string('gateway', 32)->nullable();
            $table->string('status', 16);
            $table->bigInteger('amount');
            $table->string('gateway_refund_id', 255)->nullable();
            $table->string('error', 500)->nullable();
            $table->json('details')->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $now = now();
        DB::table('return_policies')->insert(['window_days' => 14, 'restocking_fee_bps' => 0, 'return_shipping_payer' => 'customer_unless_fault', 'require_video' => true, 'allow_exchanges' => true, 'created_at' => $now, 'updated_at' => $now]);

        $reasons = [
            ['defective', 'Damaged on arrival', 'وصل تالفًا', true, true],
            ['not_as_described', 'Not as described', 'غير مطابق للوصف', true, true],
            ['wrong_item', 'Wrong item received', 'استلمت منتجًا خاطئًا', true, true],
            ['size_or_fit', 'Wrong size', 'المقاس غير مناسب', false, false],
            ['changed_mind', 'Changed mind', 'غيّرت رأيي', false, false],
            ['other', 'Other', 'سبب آخر', false, false],
        ];

        foreach ($reasons as $index => [$code, $en, $ar, $video, $fault]) {
            DB::table('return_reasons')->insert([
                'code' => $code,
                'name' => json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                'requires_video' => $video,
                'seller_fault' => $fault,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_attempts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_request_media');
        Schema::dropIfExists('return_request_events');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('return_reasons');
        Schema::dropIfExists('return_policies');
    }
};
