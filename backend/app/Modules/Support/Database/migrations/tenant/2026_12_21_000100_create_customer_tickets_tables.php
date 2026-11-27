<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer ↔ store support tickets (store database, decision #92 extension): a customer
 * opens a ticket from the app or the theme account, the store staff answer it in
 * Vendor › Support › Customers. Attachments live on the private central disk under the
 * store's folder and are streamed only by authorised routes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('subject', 250);
            $table->string('topic', 32);
            $table->uuid('order_uuid')->nullable();
            $table->string('order_number', 40)->nullable();
            $table->string('status', 20)->default('open');
            $table->string('last_reply_by', 20);
            $table->timestamp('last_reply_at')->nullable();
            $table->boolean('store_unread')->default(true);
            $table->boolean('customer_unread')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'last_reply_at']);
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('customer_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('customer_tickets')->cascadeOnDelete();
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name', 150);
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('customer_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('customer_ticket_messages')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('customer_tickets')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('original_name', 190);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ticket_attachments');
        Schema::dropIfExists('customer_ticket_messages');
        Schema::dropIfExists('customer_tickets');
    }
};
