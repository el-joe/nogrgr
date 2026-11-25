<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Sent to the other active super-admins when an admin account is created.
 */
class AdminTeamMemberAddedNotification extends PanelNotification
{
    public function __construct(public readonly string $name, public readonly string $email, public readonly string $by)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'admin_added';
    }

    protected function titleKey(): string
    {
        return 'identity::notifications.admin_added.title';
    }

    protected function bodyKey(): string
    {
        return 'identity::notifications.admin_added.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'by' => $this->by];
    }

    protected function url(): ?string
    {
        return route('admin.system.admins.index');
    }
}
