<?php

namespace App\Modules\Support\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Support ticket update for the store's own staff (vendor bell, inbox and live push on
 * `tenant.{tenant}.vendor.{id}`), stored in the tenant `notifications` table with the
 * vendor route resolved when the row is opened (both host modes). Only the staff member
 * who opened the ticket also gets the reply e-mail (MAIL-037), at $ticketUrl.
 */
class StoreSupportNotification extends SupportTicketNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     * @param  array<string, int|string>  $routeParameters
     */
    public function __construct(
        string $eventName,
        array $parameters,
        public readonly string $vendorRoute,
        public readonly array $routeParameters = [],
        public readonly ?int $mailStaffId = null,
        public readonly ?string $ticketUrl = null,
    ) {
        parent::__construct($eventName, $parameters);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('broadcasting.default') === 'reverb' ? ['database', 'broadcast'] : ['database'];

        if ($this->mailStaffId !== null && (int) ($notifiable->id ?? 0) === $this->mailStaffId && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'vendor_route' => ['name' => $this->vendorRoute, 'parameters' => $this->routeParameters]];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['item' => [
            'id' => (string) $this->id,
            'title' => (string) __($this->titleKey(), $this->params()),
            'meta' => (string) __($this->bodyKey(), $this->params()),
            'time' => now()->locale(app()->getLocale())->diffForHumans(short: true),
            'icon' => $this->category()->icon(),
            'tone' => $this->category()->tone(),
            'unread' => true,
            'url' => null,
        ]]);
    }

    protected function mailLink(): ?string
    {
        return $this->ticketUrl;
    }
}
