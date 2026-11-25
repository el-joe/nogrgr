<?php

namespace App\Modules\Catalog\Models\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * File attached to a request message, stored on the private disk and served only through
 * the authorized download route (RequestAttachmentController).
 *
 * @property int $id
 * @property int $message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property-read RequestMessage $message
 */
class RequestAttachment extends Model
{
    use CentralConnection;

    protected $table = 'catalog_request_attachments';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<RequestMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(RequestMessage::class, 'message_id');
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
