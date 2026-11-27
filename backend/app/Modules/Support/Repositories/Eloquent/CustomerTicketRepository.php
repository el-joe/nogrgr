<?php

namespace App\Modules\Support\Repositories\Eloquent;

use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\CustomerTicket;
use App\Modules\Support\Models\CustomerTicketAttachment;
use App\Modules\Support\Models\CustomerTicketMessage;
use App\Modules\Support\Repositories\Contracts\CustomerTicketRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer tickets, messages and attachments of the current store (tenant connection).
 *
 * @extends BaseRepository<CustomerTicket>
 */
class CustomerTicketRepository extends BaseRepository implements CustomerTicketRepositoryContract
{
    protected function model(): string
    {
        return CustomerTicket::class;
    }

    public function forCustomer(int $customerId, ?string $group, int $perPage): LengthAwarePaginator
    {
        return CustomerTicket::query()
            ->where('customer_id', $customerId)
            ->when($group === 'active', static fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingReply, TicketStatus::Answered]))
            ->when($group === 'closed', static fn (Builder $query) => $query->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed]))
            ->with('latestMessage')
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCustomer(int $customerId, int $id): ?CustomerTicket
    {
        return CustomerTicket::query()->where('customer_id', $customerId)->find($id);
    }

    public function listing(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        return CustomerTicket::query()
            ->with('customer:id,name,email')
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function (Builder $inner) use ($like): void {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('order_number', 'like', $like)
                        ->orWhereHas('customer', static fn (Builder $customer) => $customer->where('name', 'like', $like)->orWhere('email', 'like', $like));
                });
            })
            ->when($status === 'active', static fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingReply, TicketStatus::Answered]))
            ->when($status !== 'active' && TicketStatus::tryFrom($status) !== null, static fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('store_unread')
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id');
    }

    public function latest(): ?CustomerTicket
    {
        return CustomerTicket::query()->orderByDesc('store_unread')->orderByDesc('last_reply_at')->orderByDesc('id')->first();
    }

    public function unreadForStore(): int
    {
        return CustomerTicket::query()->where('store_unread', true)->count();
    }

    public function thread(CustomerTicket $ticket, ?int $afterId = null): Collection
    {
        return CustomerTicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->when($afterId !== null, static fn (Builder $query) => $query->where('id', '>', $afterId))
            ->with('attachments')
            ->orderBy('id')
            ->get();
    }

    public function createMessage(CustomerTicket $ticket, array $attributes): CustomerTicketMessage
    {
        return CustomerTicketMessage::query()->create(['ticket_id' => $ticket->id] + $attributes);
    }

    public function addAttachment(CustomerTicketMessage $message, array $attributes): CustomerTicketAttachment
    {
        return CustomerTicketAttachment::query()->create(['message_id' => $message->id, 'ticket_id' => $message->ticket_id] + $attributes);
    }

    public function findAttachment(int $id): ?CustomerTicketAttachment
    {
        return CustomerTicketAttachment::query()->find($id);
    }
}
