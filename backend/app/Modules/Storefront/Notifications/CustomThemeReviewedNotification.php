<?php

namespace App\Modules\Storefront\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Store owners: the platform approved or rejected an uploaded custom theme version (MAIL-034, inbox + mail + Reverb). Links to Your store › Custom theme.
 */
class CustomThemeReviewedNotification extends PanelNotification
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

    public function category(): NotificationCategory
    {
        return NotificationCategory::Requests;
    }

    public function event(): string
    {
        return 'theme_reviewed';
    }

    protected function titleKey(): string
    {
        return 'content::custom_themes.notifications.theme_reviewed.'.$this->variant.'.title';
    }

    protected function bodyKey(): string
    {
        return 'content::custom_themes.notifications.theme_reviewed.'.$this->variant.'.body';
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
