<?php

namespace App\Modules\Commerce\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Order update for admins (central inbox + mail + Reverb per their "orders" preferences)
 * and for the store's staff (tenant inbox + live push; store owners also get the mail).
 * Texts: commerce::notifications.<event>.title / .body.
 */
class OrderAlertNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly bool $forStore = false,
    ) {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $this->forStore) {
            return parent::via($notifiable);
        }

        $channels = ['database'];

        if (config('broadcasting.default') === 'reverb') {
            $channels[] = 'broadcast';
        }

        // Per-member switches (VEN-087) then drop channels via EnforceVendorNotificationPreferences.
        if (filled($notifiable->getAttribute('email'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Own editable e-mail template per event (decision #124).
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            'store_new_order' => EmailTemplateEvent::VendorNewOrder,
            'store_order_cancelled' => EmailTemplateEvent::VendorOrderCancelled,
            'store_return_opened' => EmailTemplateEvent::VendorReturnUpdate,
            'store_return_updated' => EmailTemplateEvent::VendorReturnUpdate,
            'store_return_message' => EmailTemplateEvent::VendorReturnUpdate,
            'store_refund_completed' => EmailTemplateEvent::VendorRefundUpdate,
            'store_refund_failed' => EmailTemplateEvent::VendorRefundUpdate,
            'admin_order_paid' => EmailTemplateEvent::AdminOrderAlert,
            'admin_order_cancelled' => EmailTemplateEvent::AdminOrderAlert,
            'admin_return_opened' => EmailTemplateEvent::AdminReturnAlert,
            'admin_return_updated' => EmailTemplateEvent::AdminReturnAlert,
            'admin_return_message' => EmailTemplateEvent::AdminReturnAlert,
            'admin_refund_completed' => EmailTemplateEvent::AdminReturnAlert,
            'admin_refund_failed' => EmailTemplateEvent::AdminReturnAlert,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Orders;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'commerce::notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'commerce::notifications.'.$this->eventName.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->parameters;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        if (! $this->forStore) {
            return parent::toBroadcast($notifiable);
        }

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
}
