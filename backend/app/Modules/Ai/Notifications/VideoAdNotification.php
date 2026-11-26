<?php

namespace App\Modules\Ai\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * The staff member who ordered an AI video ad: it is ready to watch / download, or it
 * failed (try again from the Advertising Library). Vendor inbox + mail + Reverb.
 * Texts: ai::ad_studio.notifications.<event>.title / .body.
 */
class VideoAdNotification extends PanelNotification
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
        return 'video_ad_'.$this->eventName;
    }

    protected function titleKey(): string
    {
        return 'ai::ad_studio.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'ai::ad_studio.notifications.'.$this->eventName.'.body';
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
