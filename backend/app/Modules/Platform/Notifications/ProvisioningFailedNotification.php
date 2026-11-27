<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / mail / live push: a store failed to provision ("vendors.provisioning_failed").
 */
class ProvisioningFailedNotification extends PanelNotification
{
    public function __construct(public readonly int $tenantId, public readonly string $storeName)
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
            'provisioning_failed' => EmailTemplateEvent::AdminVendorAlert,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Vendors;
    }

    public function event(): string
    {
        return 'provisioning_failed';
    }

    protected function titleKey(): string
    {
        return 'platform::notifications.provisioning_failed.title';
    }

    protected function bodyKey(): string
    {
        return 'platform::notifications.provisioning_failed.body';
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
        return route('admin.vendors.show', $this->tenantId);
    }
}
