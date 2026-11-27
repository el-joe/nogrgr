<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
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

    /**
     * Own editable e-mail template per event (decision #124).
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            'role_changed' => EmailTemplateEvent::AdminTeamUpdate,
        ];
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
