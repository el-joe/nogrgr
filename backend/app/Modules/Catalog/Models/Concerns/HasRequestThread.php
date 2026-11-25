<?php

namespace App\Modules\Catalog\Models\Concerns;

use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Conversation, payment requests and admin-unread helpers of a ThreadedRequest model.
 *
 * @phpstan-require-extends Model
 */
trait HasRequestThread
{
    /**
     * @return MorphMany<RequestMessage, $this>
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(RequestMessage::class, 'requestable')->orderBy('id');
    }

    /**
     * @return MorphMany<RequestPayment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(RequestPayment::class, 'requestable')->latest('id');
    }

    public function tenantId(): int
    {
        return (int) $this->getAttribute('tenant_id');
    }

    public function reference(): string
    {
        return $this->kind()->referencePrefix().'-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function statusTone(): string
    {
        return $this->status->tone();
    }
}
