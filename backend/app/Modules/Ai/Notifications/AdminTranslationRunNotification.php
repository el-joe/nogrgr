<?php

namespace App\Modules\Ai\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admins: an AI translation run of the catalogue or of the static keys finished or
 * failed (ADM-091b / ADM-092a). Admin inbox + mail + Reverb.
 * Texts: ai::admin_translation.notifications.<event>.title / .body.
 */
class AdminTranslationRunNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly ?string $link = null,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'ai::admin_translation.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'ai::admin_translation.notifications.'.$this->eventName.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->parameters;
    }

    protected function url(): ?string
    {
        return $this->link;
    }
}
