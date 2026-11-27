<?php

namespace App\Modules\Billing\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Store finance alerts (decision #72): payout scheduled / paid / cancelled, settlement
 * confirmed / paid (receipt), settlement due reminder, platform gateway limit warning / block. Store owners get the panel inbox + live push (Reverb) + mail.
 */
class FinanceAlertNotification extends PanelNotification
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
     * Own editable e-mail template per event (decision #124).
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            'payout_scheduled' => EmailTemplateEvent::VendorPayoutUpdate,
            'payout_paid' => EmailTemplateEvent::VendorPayoutUpdate,
            'payout_cancelled' => EmailTemplateEvent::VendorPayoutUpdate,
            'settlement_confirmed' => EmailTemplateEvent::VendorSettlementReceipt,
            'settlement_paid' => EmailTemplateEvent::VendorSettlementReceipt,
            'settlement_due' => EmailTemplateEvent::VendorSettlementDue,
            'gateway_limit_warning' => EmailTemplateEvent::VendorGatewayLimit,
            'gateway_limit_block' => EmailTemplateEvent::VendorGatewayLimit,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Payments;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'billing::finance.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'billing::finance.notifications.'.$this->eventName.'.body';
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
     * Settlement templates also get the amount / reference / order (decision #124).
     *
     * @return array<string, string>
     */
    protected function alertVariables(object $notifiable): array
    {
        return parent::alertVariables($notifiable) + [
            'settlement.amount' => (string) ($this->parameters['amount'] ?? ''),
            'settlement.reference' => (string) ($this->parameters['reference'] ?? ''),
            'order.number' => (string) ($this->parameters['order'] ?? ''),
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
