<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Sent to a new admin: their account exists and where to sign in.
 */
class AdminWelcomeNotification extends PanelNotification
{
    public function __construct(public readonly string $name)
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
            'admin_welcome' => EmailTemplateEvent::AdminWelcome,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'admin_welcome';
    }

    protected function titleKey(): string
    {
        return 'identity::notifications.admin_welcome.title';
    }

    protected function bodyKey(): string
    {
        return 'identity::notifications.admin_welcome.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['name' => $this->name];
    }

    protected function url(): ?string
    {
        return route('admin.account.edit');
    }
}
