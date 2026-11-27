<?php

namespace App\Modules\Support\Models;

use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Identity\Models\Customer;
use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A customer ↔ store support ticket (store database). Reference `C-<1000+id>`; status uses
 * the shared TicketStatus lifecycle (customer = requester, store staff = responder).
 *
 * @property int $id
 * @property string|null $reference
 * @property int $customer_id
 * @property string $subject
 * @property SupportSubject $topic
 * @property string|null $order_uuid
 * @property string|null $order_number
 * @property TicketStatus $status
 * @property TicketAuthor $last_reply_by
 * @property Carbon|null $last_reply_at
 * @property bool $store_unread
 * @property bool $customer_unread
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, CustomerTicketMessage> $messages
 * @property-read CustomerTicketMessage|null $latestMessage
 */
class CustomerTicket extends Model
{
    protected $table = 'customer_tickets';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<CustomerTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerTicketMessage::class, 'ticket_id');
    }

    /**
     * @return HasOne<CustomerTicketMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(CustomerTicketMessage::class, 'ticket_id')->latestOfMany();
    }

    public function displayReference(): string
    {
        return $this->reference ?? 'C-'.(1000 + $this->id);
    }

    public function title(): string
    {
        return $this->displayReference().' · '.$this->subject;
    }

    /**
     * Customers may write while the ticket is not closed (a reply to a resolved one reopens it).
     */
    public function customerCanReply(): bool
    {
        return ! $this->status->isClosed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => SupportSubject::class,
            'status' => TicketStatus::class,
            'last_reply_by' => TicketAuthor::class,
            'last_reply_at' => 'datetime',
            'store_unread' => 'boolean',
            'customer_unread' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
