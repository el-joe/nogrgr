<?php

namespace App\Modules\Billing\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / mail / live Reverb push for a billing event ("payments.invoice_paid",
 * "vendors.subscription_expired"…). Channels follow each admin's category preferences.
 * Texts: billing::notifications.<event>.title / .body with the given params.
 */
class BillingAlertNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly NotificationCategory $notificationCategory,
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly ?string $link = null,
    ) {
        parent::__construct();
    }

    /**
     * Own editable e-mail template per event (decision #124).
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            'payment_failed' => EmailTemplateEvent::AdminPaymentFailed,
            'invoice_issued' => EmailTemplateEvent::AdminBillingAlert,
            'invoice_paid' => EmailTemplateEvent::AdminBillingAlert,
            'invoice_voided' => EmailTemplateEvent::AdminBillingAlert,
            'payment_refunded' => EmailTemplateEvent::AdminBillingAlert,
            'subscription_activated' => EmailTemplateEvent::AdminBillingAlert,
            'subscription_past_due' => EmailTemplateEvent::AdminBillingAlert,
            'subscription_expired' => EmailTemplateEvent::AdminBillingAlert,
            'subscription_cancelled' => EmailTemplateEvent::AdminBillingAlert,
            'subscription_renewed' => EmailTemplateEvent::AdminBillingAlert,
        ];
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
        return 'billing::notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'billing::notifications.'.$this->eventName.'.body';
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
