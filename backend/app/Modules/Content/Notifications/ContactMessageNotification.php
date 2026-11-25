<?php

namespace App\Modules\Content\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / bell / mail: a new central contact-form message (MAIL-041).
 */
class ContactMessageNotification extends PanelNotification
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'contact_message';
    }

    protected function titleKey(): string
    {
        return 'content::notifications.contact.title';
    }

    protected function bodyKey(): string
    {
        return 'content::notifications.contact.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'subject' => $this->subject];
    }
}
