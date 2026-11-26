<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Store owners: an invited team member joined (tenant inbox + mail + Reverb, per the
 * owner's "system" preferences). Links to Settings › Staff.
 */
class VendorTeamMemberJoinedNotification extends PanelNotification
{
    public function __construct(
        public readonly string $name,
        public readonly string $role,
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
        return 'team_member_joined';
    }

    protected function titleKey(): string
    {
        return 'identity::notifications.team_member_joined.title';
    }

    protected function bodyKey(): string
    {
        return 'identity::notifications.team_member_joined.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['name' => $this->name, 'role' => $this->role];
    }

    protected function url(): ?string
    {
        return $this->link;
    }
}
