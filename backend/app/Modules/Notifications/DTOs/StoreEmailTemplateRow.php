<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use Illuminate\Support\Carbon;

/**
 * One row of Vendor › Settings › Email templates: a shopper event with the platform
 * template (if any) and the store's copy (if any).
 */
final readonly class StoreEmailTemplateRow
{
    public function __construct(
        public EmailTemplateEvent $event,
        public ?EmailTemplate $platform,
        public ?StoreEmailTemplate $store,
    ) {}

    public function isCustomized(): bool
    {
        return $this->store?->is_customized === true;
    }

    /**
     * What the store's shoppers receive: custom | platform | builtin.
     */
    public function source(): string
    {
        if ($this->isCustomized() && $this->store?->completeLocales() !== []) {
            return 'custom';
        }

        return $this->platform !== null && $this->platform->status->value === 'active' && $this->platform->completeLocales() !== [] ? 'platform' : 'builtin';
    }

    /**
     * Template the editor starts from: the store's copy, else the platform's.
     */
    public function content(): StoreEmailTemplate|EmailTemplate|null
    {
        return $this->store ?? $this->platform;
    }

    /**
     * @return list<string>
     */
    public function completeLocales(): array
    {
        return $this->content()?->completeLocales() ?? [];
    }

    public function updatedAt(): ?Carbon
    {
        return $this->isCustomized() ? $this->store?->updated_at : $this->platform?->updated_at;
    }
}
