<?php

namespace App\Modules\Storefront\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Store owners: the live custom theme failed to render and the storefront fell back to the built-in theme (SF-ALL-006). Sent at most once an hour per version.
 */
class CustomThemeRenderFailedNotification extends PanelNotification
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
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'theme_render_failed';
    }

    protected function titleKey(): string
    {
        return 'content::custom_themes.notifications.theme_render_failed.'.$this->variant.'.title';
    }

    protected function bodyKey(): string
    {
        return 'content::custom_themes.notifications.theme_render_failed.'.$this->variant.'.body';
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
