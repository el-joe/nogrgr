<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / mail / live push: a new store is live ("vendors.store_provisioned").
 */
class VendorProvisionedNotification extends PanelNotification
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
            'store_provisioned' => EmailTemplateEvent::AdminNewVendor,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Vendors;
    }

    public function event(): string
    {
        return 'store_provisioned';
    }

    protected function titleKey(): string
    {
        return 'platform::notifications.store_provisioned.title';
    }

    protected function bodyKey(): string
    {
        return 'platform::notifications.store_provisioned.body';
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
