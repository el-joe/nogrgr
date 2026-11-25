<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Security notice to an admin whose password changed.
 */
class AdminPasswordChangedNotification extends PanelNotification
{
    public function __construct(public readonly string $time)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'password_changed';
    }

    protected function titleKey(): string
    {
        return 'identity::notifications.password_changed.title';
    }

    protected function bodyKey(): string
    {
        return 'identity::notifications.password_changed.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['time' => $this->time];
    }

    protected function url(): ?string
    {
        return route('admin.account.edit');
    }
}
