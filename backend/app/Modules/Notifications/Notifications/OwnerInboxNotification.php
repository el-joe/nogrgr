<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;

/**
 * Owner-portal inbox copy (OWN-008: database + Reverb on "owner.{id}") of an e-mail the
 * store owner already received (billing, compliance, domain, renewal reminder; decision
 * #124). Never mails itself: the e-mail is the templated notification next to it.
 */
class OwnerInboxNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly NotificationCategory $notificationCategory,
        public readonly string $eventName,
        public readonly string $titleTranslationKey,
        public readonly string $bodyTranslationKey,
        public readonly array $parameters = [],
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
        return $this->notificationCategory;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return $this->titleTranslationKey;
    }

    protected function bodyKey(): string
    {
        return $this->bodyTranslationKey;
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
