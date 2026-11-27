<?php

namespace App\Modules\Storefront\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Platform admins (storefront.theme-reviews.manage): a store submitted a custom theme version for review (ADM-089). Links to the review detail.
 */
class CustomThemeSubmittedNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public function __construct(
        public readonly string $variant,
        public readonly array $replace,
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
            'theme_submitted' => EmailTemplateEvent::AdminRequestAlert,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Requests;
    }

    public function event(): string
    {
        return 'theme_submitted';
    }

    protected function titleKey(): string
    {
        return 'content::custom_themes.notifications.theme_submitted.'.$this->variant.'.title';
    }

    protected function bodyKey(): string
    {
        return 'content::custom_themes.notifications.theme_submitted.'.$this->variant.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->replace;
    }

    protected function url(): ?string
    {
        return $this->link;
    }
}
