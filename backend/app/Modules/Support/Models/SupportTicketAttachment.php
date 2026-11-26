<?php

namespace App\Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * File attached to a ticket message, on the private central disk; served only through
 * the authorised download routes of each panel.
 *
 * @property int $id
 * @property int $message_id
 * @property int $ticket_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property-read SupportTicketMessage $message
 * @property-read SupportTicket $ticket
 */
class SupportTicketAttachment extends Model
{
    use CentralConnection;

    protected $table = 'support_ticket_attachments';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<SupportTicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'message_id');
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size, 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
