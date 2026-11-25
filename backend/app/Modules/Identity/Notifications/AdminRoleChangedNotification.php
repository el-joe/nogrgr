<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Sent to an admin whose role changed.
 */
class AdminRoleChangedNotification extends PanelNotification
{
    public function __construct(public readonly string $role)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'role_changed';
    }

    protected function titleKey(): string
    {
        return 'identity::notifications.role_changed.title';
    }

    protected function bodyKey(): string
    {
        return 'identity::notifications.role_changed.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['role' => $this->role];
    }

    protected function url(): ?string
    {
        return route('admin.account.edit');
    }
}
