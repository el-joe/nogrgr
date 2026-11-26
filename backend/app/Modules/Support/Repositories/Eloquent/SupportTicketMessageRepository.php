<?php

namespace App\Modules\Support\Repositories\Eloquent;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Repositories\Contracts\SupportTicketMessageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use App\Shared\Tenancy\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ticket messages and attachments. Messages are read through their (already authorised)
 * ticket, so the tenant scope is lifted: an owner-portal message of a store ticket has the
 * store's tenant_id while the owner reads it centrally.
 *
 * @extends BaseRepository<SupportTicketMessage>
 */
class SupportTicketMessageRepository extends BaseRepository implements SupportTicketMessageRepositoryContract
{
    protected function model(): string
    {
        return SupportTicketMessage::class;
    }

    public function thread(SupportTicket $ticket, ?int $afterId = null, bool $withInternal = false): Collection
    {
        return SupportTicketMessage::query()
            ->withoutGlobalScope(CurrentTenantScope::class)
            ->where('ticket_id', $ticket->id)
            ->when(! $withInternal, static fn ($query) => $query->where('is_internal', false))
            ->when($afterId !== null, static fn ($query) => $query->where('id', '>', $afterId))
            ->with('attachments')
            ->orderBy('id')
            ->get();
    }

    public function createFor(SupportTicket $ticket, array $attributes): SupportTicketMessage
    {
        return SupportTicketMessage::query()->create([...$attributes, 'ticket_id' => $ticket->id, 'tenant_id' => $ticket->tenant_id]);
    }

    public function addAttachment(SupportTicketMessage $message, array $attributes): SupportTicketAttachment
    {
        return SupportTicketAttachment::query()->create([...$attributes, 'message_id' => $message->id, 'ticket_id' => $message->ticket_id]);
    }

    public function attachments(SupportTicket $ticket, bool $withInternal = false): Collection
    {
        return SupportTicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->when(! $withInternal, static fn ($query) => $query->whereHas('message', static fn ($message) => $message->withoutGlobalScope(CurrentTenantScope::class)->where('is_internal', false)))
            ->orderBy('id')
            ->get();
    }

    public function findAttachment(int $id): ?SupportTicketAttachment
    {
        return SupportTicketAttachment::query()->with(['message' => static fn ($query) => $query->withoutGlobalScope(CurrentTenantScope::class)])->find($id);
    }
}
