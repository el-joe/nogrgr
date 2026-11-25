<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fulfilment (P5 batch 3, decision #69): the shipping progress of an order next to its
| OrderStatus (pending → packed → in transit → out for delivery / delayed → delivered),
| the branch that shipped the central lines, the cancellation reason code, carrier
| tracking events and the store's primary payment gateway.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfilment_status', 24)->default('pending')->index();
            $table->unsignedBigInteger('fulfilment_branch_id')->nullable();
            $table->string('cancel_reason_code', 32)->nullable();
        });

        Schema::create('order_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('location', 160)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('store_payment_gateways', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('store_payment_gateways', fn (Blueprint $table) => $table->dropColumn('is_primary'));
        Schema::dropIfExists('order_tracking_events');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['fulfilment_status', 'fulfilment_branch_id', 'cancel_reason_code']));
    }
};
