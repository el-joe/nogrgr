<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Storefront engagement alert for the store's owners (tenant inbox + mail + Reverb per
 * their "requests" preferences): new support request, new product review.
 * Texts: engagement::notifications.<event>.title / .body.
 */
class StoreEngagementNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
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
            'review_pending' => EmailTemplateEvent::VendorReviewUpdate,
            'review_published' => EmailTemplateEvent::VendorReviewUpdate,
            'support_request' => EmailTemplateEvent::VendorContactMessage,
        ];
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
        return 'engagement::notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'engagement::notifications.'.$this->eventName.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->parameters;
    }
}
