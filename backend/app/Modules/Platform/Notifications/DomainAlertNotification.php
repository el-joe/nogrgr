<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox (+ Reverb) alert about a store's custom domain, e.g. its DNS check failed
 * (decision #124: database only, no mail). Texts: platform::notifications.<event>.
 */
class DomainAlertNotification extends PanelNotification
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

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_diff(parent::via($notifiable), ['mail']));
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Vendors;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'platform::notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'platform::notifications.'.$this->eventName.'.body';
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
