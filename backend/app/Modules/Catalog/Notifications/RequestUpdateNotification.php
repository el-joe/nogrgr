<?php

namespace App\Modules\Catalog\Notifications;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Requests-center update for the store owner (admin reply, status change, payment
 * request, review decision) or for admins (vendor reply). Database + mail + Reverb via
 * the shared PanelNotification base; an on-demand mail route (store without an owner
 * account) gets the e-mail only. Texts: catalog::requests.notifications.<event>.
 */
class RequestUpdateNotification extends PanelNotification
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly ?string $link = null,
    ) {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : parent::via($notifiable);
    }

    /**
     * Own editable e-mail template per event (decision #124).
     *
     * @return array<string, EmailTemplateEvent>
     */
    public static function specificMailTemplates(): array
    {
        return [
            'product_assigned' => EmailTemplateEvent::VendorRequestUpdate,
            'admin_replied' => EmailTemplateEvent::VendorRequestUpdate,
            'status_changed' => EmailTemplateEvent::VendorRequestUpdate,
            'payment_requested' => EmailTemplateEvent::VendorRequestUpdate,
            'payment_cancelled' => EmailTemplateEvent::VendorRequestUpdate,
            'edit_approved' => EmailTemplateEvent::VendorRequestUpdate,
            'edit_rejected' => EmailTemplateEvent::VendorRequestUpdate,
            'tenant_change_approved' => EmailTemplateEvent::VendorRequestUpdate,
            'tenant_change_rejected' => EmailTemplateEvent::VendorRequestUpdate,
            'vendor_replied' => EmailTemplateEvent::AdminRequestAlert,
            'vendor_submitted' => EmailTemplateEvent::AdminRequestAlert,
            'vendor_cancelled' => EmailTemplateEvent::AdminRequestAlert,
        ];
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Requests;
    }

    public function event(): string
    {
        return $this->eventName;
    }

    protected function titleKey(): string
    {
        return 'catalog::requests.notifications.'.$this->eventName.'.title';
    }

    protected function bodyKey(): string
    {
        return 'catalog::requests.notifications.'.$this->eventName.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return $this->parameters;
    }

    protected function url(): ?string
    {
        return $this->link;
    }
}
