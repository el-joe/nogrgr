<?php

namespace App\Modules\Catalog\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Throwable;

/**
 * Requests-center update for the store's own staff (vendor bell + inbox): the tenant
 * `notifications` table and the live push on `tenant.{tenant}.vendor.{id}`. No mail —
 * the owner already gets the e-mail through RequestUpdateNotification. The link is a
 * vendor route name resolved when the row is opened, so it works in both host modes.
 */
class StoreRequestNotification extends RequestUpdateNotification
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
    ) {
        parent::__construct($eventName, $parameters);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('broadcasting.default') === 'reverb' ? ['database', 'broadcast'] : ['database'];

        // Staff mail follows the request_replies switch (VEN-087, EnforceVendorNotificationPreferences, #124).
        if (filled($notifiable->email ?? null)) {
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

    /**
     * Bell row pushed live; the page builds the open link from its own host (openUrl).
     */
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

    /**
     * E-mail button for store staff: the request page in the vendor panel.
     */
    protected function url(): ?string
    {
        try {
            return vendor_route($this->vendorRoute, $this->routeParameters);
        } catch (Throwable) {
            return parent::url();
        }
    }
}
