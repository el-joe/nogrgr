<?php

namespace App\Modules\Inventory\Notifications;

use App\Modules\Inventory\Enums\StockAlertEvent;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Low stock / out of stock alert of the store team (tenant inbox + Reverb + mail
 * `vendor.low_stock`); the member's VEN-087 "low stock" switches apply per channel
 * (mail off by default). Texts: inventory::alerts.<event>.title / .body.
 */
class StockAlertNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters  product, stock, threshold, store
     */
    public function __construct(
        public readonly StockAlertEvent $alert,
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
        $channels = ['database'];

        if (config('broadcasting.default') === 'reverb') {
            $channels[] = 'broadcast';
        }

        if (filled($notifiable->getAttribute('email'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            StockAlertEvent::LowStock->value => EmailTemplateEvent::VendorLowStock,
            StockAlertEvent::OutOfStock->value => EmailTemplateEvent::VendorLowStock,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return $this->alert->value;
    }

    protected function titleKey(): string
    {
        return 'inventory::alerts.'.$this->alert->value.'.title';
    }

    protected function bodyKey(): string
    {
        return 'inventory::alerts.'.$this->alert->value.'.body';
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

    /**
     * @return array<string, string>
     */
    protected function alertVariables(object $notifiable): array
    {
        return parent::alertVariables($notifiable) + [
            'product.name' => (string) ($this->parameters['product'] ?? ''),
            'product.stock' => (string) ($this->parameters['stock'] ?? ''),
            'product.threshold' => (string) ($this->parameters['threshold'] ?? ''),
        ];
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
            'url' => $this->link,
        ]]);
    }
}
