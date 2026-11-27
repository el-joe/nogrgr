<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment receipt for a paid central sign-up (WEB-019a "receipt was sent to …"). The
 * subscription invoice is issued and settled with this payment when the store is created.
 */
class RegistrationPaymentReceiptNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct(
        public readonly string $planName,
        public readonly string $term,
        public readonly string $amount,
        public readonly string $reference,
    ) {
        $this->onQueue('notifications');
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
        return [EmailTemplateEvent::VendorRegistrationReceipt];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorRegistrationReceipt;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorRegistrationReceipt, [
            'plan.name' => $this->planName,
            'plan.term' => $this->term,
            'payment.amount' => $this->amount,
            'payment.reference' => $this->reference,
        ]);
    }
}
