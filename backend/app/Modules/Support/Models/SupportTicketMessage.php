<?php

namespace App\Modules\Support\Models;

use App\Modules\Support\Enums\TicketAuthor;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One message of a support ticket. `is_internal` = admin note, never shown to the
 * requester (store staff / owner).
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $tenant_id
 * @property TicketAuthor $author_type
 * @property int|null $author_id
 * @property string $author_name
 * @property string|null $body
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 * @property-read Collection<int, SupportTicketAttachment> $attachments
 */
class SupportTicketMessage extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'support_ticket_messages';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * @return HasMany<SupportTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'message_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->author_type === TicketAuthor::Admin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author_type' => TicketAuthor::class,
            'is_internal' => 'boolean',
        ];
    }
}
