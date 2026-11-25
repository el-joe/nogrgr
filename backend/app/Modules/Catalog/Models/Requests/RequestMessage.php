<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Enums\MessageSender;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One message of a request conversation. `read_at` = read by the other side
 * (an admin for vendor messages, the vendor for admin messages).
 *
 * @property int $id
 * @property string $requestable_type
 * @property int $requestable_id
 * @property int $tenant_id
 * @property MessageSender $sender
 * @property int|null $admin_id
 * @property string $sender_name
 * @property string|null $body
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RequestAttachment> $attachments
 */
class RequestMessage extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'catalog_request_messages';

    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<RequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'message_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->sender === MessageSender::Admin;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender' => MessageSender::class,
            'read_at' => 'datetime',
        ];
    }
}
