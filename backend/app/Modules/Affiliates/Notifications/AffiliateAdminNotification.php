<?php

namespace App\Modules\Affiliates\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Affiliate program alert for admins (inbox + mail + Reverb per their "payments"
 * preferences): payout requested (`admin.affiliate_payout_request`). Texts:
 * affiliates::notifications.admin.<event>.
 */
class AffiliateAdminNotification extends PanelNotification
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
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return ['payout_requested' => EmailTemplateEvent::AdminAffiliatePayoutRequest];
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
        return 'affiliates::notifications.admin.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'affiliates::notifications.admin.'.$this->eventName.'.body';
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
