<?php

namespace App\Modules\Support\Models;

use App\Modules\Support\Enums\TicketAuthor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One message of a customer ticket: the customer (TicketAuthor::Customer) or a store
 * staff member (TicketAuthor::Vendor).
 *
 * @property int $id
 * @property int $ticket_id
 * @property TicketAuthor $author_type
 * @property int|null $author_id
 * @property string $author_name
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerTicket $ticket
 * @property-read Collection<int, CustomerTicketAttachment> $attachments
 */
class CustomerTicketMessage extends Model
{
    protected $table = 'customer_ticket_messages';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<CustomerTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class, 'ticket_id');
    }

    /**
     * @return HasMany<CustomerTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerTicketAttachment::class, 'message_id');
    }

    public function isFromStore(): bool
    {
        return $this->author_type === TicketAuthor::Vendor;
    }

    public function isFromCustomer(): bool
    {
        return $this->author_type === TicketAuthor::Customer;
    }

    /**
     * Never a platform message (shared thread partial).
     */
    public function isFromAdmin(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['author_type' => TicketAuthor::class];
    }
}
