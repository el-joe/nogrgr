<?php

namespace App\Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * File attached to a customer ticket message (private central disk, store folder);
 * streamed by the vendor route or behind a temporary signed Store API URL.
 *
 * @property int $id
 * @property int $message_id
 * @property int $ticket_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property-read CustomerTicketMessage $message
 * @property-read CustomerTicket $ticket
 */
class CustomerTicketAttachment extends Model
{
    protected $table = 'customer_ticket_attachments';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<CustomerTicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CustomerTicketMessage::class, 'message_id');
    }

    /**
     * @return BelongsTo<CustomerTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CustomerTicket::class, 'ticket_id');
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
