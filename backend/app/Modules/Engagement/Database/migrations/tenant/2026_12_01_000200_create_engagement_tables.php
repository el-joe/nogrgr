<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Store engagement (tenant DB): product reviews (one per delivered order line),
| customer favorites, support requests (SF-ECO-035) and newsletter subscribers.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('store_product_id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('comment');
            $table->string('author_name', 120);
            $table->string('locale', 8)->nullable();
            $table->string('status', 16)->index();
            $table->string('moderation_note', 500)->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['store_product_id', 'status', 'created_at']);
        });

        Schema::create('customer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('store_product_id')->index();
            $table->timestamp('created_at')->nullable();
            $table->unique(['customer_id', 'store_product_id']);
        });

        Schema::create('support_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('phone', 32)->nullable();
            $table->string('order_number', 32)->nullable();
            $table->string('subject', 32);
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->string('status', 16)->default('new')->index();
            $table->string('locale', 8)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('store_newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 160)->unique();
            $table->string('locale', 8)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_newsletter_subscribers');
        Schema::dropIfExists('support_requests');
        Schema::dropIfExists('customer_favorites');
        Schema::dropIfExists('product_reviews');
    }
};
