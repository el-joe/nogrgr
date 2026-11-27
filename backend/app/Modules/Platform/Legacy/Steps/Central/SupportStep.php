<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;

/**
 * Vendor ↔ platform support tickets + messages (old central `support_tickets`).
 * Reference = T-<1000 + new id> like new tickets. Old attachments are not copied
 * (reported). Skips tickets of stores that were not migrated.
 */
class SupportStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Support;
    }

    public function run(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();

        foreach ($source->table('support_tickets')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'support_tickets');
            $tenantId = $writer->id('tenant', (string) $row->tenant_id);

            if ($tenantId === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'support_tickets', (int) $row->id, 'Ticket of a store that was not migrated (skipped)');
                $context->report->skipped('central', 'support_tickets');

                continue;
            }

            $tenant = $writer->connection()->table('tenants')->where('id', $tenantId)->first(['name', 'owner_email']);
            $status = match ((string) $row->status) {
                'closed' => TicketStatus::Closed,
                'resolved' => TicketStatus::Resolved,
                'answered' => TicketStatus::Answered,
                default => TicketStatus::Open,
            };
            $ticketId = $writer->upsert('support_ticket', (int) $row->id, 'support_tickets', [
                'tenant_id' => $tenantId,
                'opened_by_type' => TicketAuthor::Vendor->value,
                'opened_by_name' => (string) ($tenant->name ?? 'Store'),
                'opened_by_email' => $tenant->owner_email ?? null,
                'subject' => mb_substr((string) $row->subject, 0, 255),
                'category' => (TicketCategory::tryFrom((string) $row->category) ?? TicketCategory::Other)->value,
                'priority' => (TicketPriority::tryFrom((string) $row->priority) ?? TicketPriority::Normal)->value,
                'status' => $status->value,
                'last_reply_at' => $row->last_reply_at,
                'admin_unread' => (bool) $row->admin_has_unread,
                'requester_unread' => (bool) $row->tenant_has_unread,
                'created_at' => $row->created_at,
            ]);

            if (! $context->dryRun() && $ticketId > 0) {
                $writer->connection()->table('support_tickets')->where('id', $ticketId)->update(['reference' => 'T-'.(1000 + $ticketId)]);
            }

            foreach ($source->table('support_ticket_messages')->where('ticket_id', $row->id)->orderBy('id')->get() as $message) {
                $context->report->read('central', 'support_ticket_messages');

                if (($attachments = json_decode((string) $message->attachments, true)) !== null && $attachments !== []) {
                    $context->report->issue(LegacyIssueLevel::Info, 'central', 'support_ticket_messages', (int) $message->id, 'Message attachments not copied');
                }

                $writer->upsert('support_ticket_message', (int) $message->id, 'support_ticket_messages', [
                    'ticket_id' => $ticketId,
                    'tenant_id' => $tenantId,
                    'author_type' => ($message->sender_type === 'admin' ? TicketAuthor::Admin : TicketAuthor::Vendor)->value,
                    'author_name' => (string) $message->sender_name,
                    'body' => (string) $message->body,
                    'is_internal' => false,
                    'created_at' => $message->created_at,
                ]);
            }
        }
    }
}
