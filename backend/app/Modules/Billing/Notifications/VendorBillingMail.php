<?php

namespace App\Modules\Billing\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Billing e-mail to a store owner (on-demand mail route). Uses the admin's active
 * e-mail template of the event (Admin › Settings › Email templates) and falls back
 * to the built-in billing::notifications.<event>.vendor_* texts.
 */
class VendorBillingMail extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    /**
     * @param  array<string, string>  $variables  template variables (see EmailTemplateEvent::variables())
     */
    public function __construct(
        public readonly EmailTemplateEvent $template,
        public readonly string $eventName,
        public readonly array $variables,
        public readonly string $mailLocale,
        public readonly ?string $link = null,
    ) {
        $this->onQueue('mail');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [
            EmailTemplateEvent::VendorInvoiceIssued, EmailTemplateEvent::VendorInvoicePaid, EmailTemplateEvent::VendorPaymentFailed, EmailTemplateEvent::VendorPaymentRefunded,
            EmailTemplateEvent::VendorSubscriptionActivated, EmailTemplateEvent::VendorSubscriptionPastDue, EmailTemplateEvent::VendorSubscriptionExpired,
            EmailTemplateEvent::VendorSubscriptionCancelled, EmailTemplateEvent::VendorSubscriptionRenewed, EmailTemplateEvent::VendorInvoiceVoided,
            EmailTemplateEvent::VendorSubscriptionRenewalReminder,
        ];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return $this->template;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail($this->template, $this->variables, $this->mailLocale);
    }
}
