<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Back-in-stock subscriptions (tenant DB, decision #124): a shopper (signed-in customer
| or a guest e-mail) asks to be told when an out-of-stock store product returns. One row
| per product + e-mail; `notified_at` marks the single notice already sent (subscribing
| again re-arms it).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alert_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('store_product_id');
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('email', 191);
            $table->string('locale', 8)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['store_product_id', 'email']);
            $table->index(['store_product_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alert_subscriptions');
    }
};
