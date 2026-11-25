<?php

namespace App\Modules\Catalog\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Requests-center update for the store owner (admin reply, status change, payment
 * request, review decision) or for admins (vendor reply). Database + mail + Reverb via
 * the shared PanelNotification base; an on-demand mail route (store without an owner
 * account) gets the e-mail only. Texts: catalog::requests.notifications.<event>.
 */
class RequestUpdateNotification extends PanelNotification
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

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : parent::via($notifiable);
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Requests;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'catalog::requests.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'catalog::requests.notifications.'.$this->eventName.'.body';
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
