<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets (ADM-116, VEN-091, OWN-009 — central DB, decision #92).
 *
 *  - support_tickets: opened by store staff (tenant_id set) or by the owner from the owner
 *    portal (owner_id set, tenant_id = the picked store or NULL for account questions).
 *    `admin_unread` / `requester_unread` drive the sidebar badges; `sla_due_at` = when the
 *    platform must answer the requester's latest message (by priority).
 *  - support_ticket_messages: admin / vendor / owner messages; `is_internal` = admin-only
 *    note, never shown to the store or the owner.
 *  - support_ticket_attachments: files on the private central disk, served only through the
 *    authorised download routes.
 *  - support_help_feedback: "Was this helpful?" votes of the vendor help center.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 24)->nullable()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
            $table->string('opened_by_type', 16);
            $table->unsignedBigInteger('opened_by_id')->nullable();
            $table->string('opened_by_name');
            $table->string('opened_by_email')->nullable();
            $table->string('subject');
            $table->string('category', 24);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('last_reply_by', 16)->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->boolean('admin_unread')->default(true);
            $table->boolean('requester_unread')->default(false);
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_reply_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index('assigned_admin_id');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('author_type', 16);
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name');
            $table->text('body')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('support_ticket_messages')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name', 190);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('support_help_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id');
            $table->string('article', 80);
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['tenant_id', 'staff_id', 'article']);
            $table->index('article');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_help_feedback');
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
