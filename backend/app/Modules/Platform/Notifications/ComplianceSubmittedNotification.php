<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / mail / live push: a store submitted or re-submitted its compliance
 * center ("vendors.compliance_submitted" / "vendors.compliance_resubmitted").
 */
class ComplianceSubmittedNotification extends PanelNotification
{
    public function __construct(
        public readonly int $profileId,
        public readonly string $storeName,
        public readonly bool $resubmitted = false,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Vendors;
    }

    public function event(): string
    {
        return $this->resubmitted ? 'compliance_resubmitted' : 'compliance_submitted';
    }

    protected function titleKey(): string
    {
        return 'platform::notifications.'.$this->event().'.title';
    }

    protected function bodyKey(): string
    {
        return 'platform::notifications.'.$this->event().'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return ['store' => $this->storeName];
    }

    protected function url(): ?string
    {
        return route('admin.vendors.compliance.show', $this->profileId);
    }
}
