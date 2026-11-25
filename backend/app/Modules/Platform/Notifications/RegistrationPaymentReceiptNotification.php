<?php

namespace App\Modules\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment receipt for a paid central sign-up (WEB-019a "receipt was sent to …"). The
 * subscription invoice is issued and settled with this payment when the store is created.
 */
class RegistrationPaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('platform::mail.registration_receipt.subject', ['app' => config('app.name')]))
            ->greeting(__('platform::mail.registration_receipt.greeting'))
            ->line(__('platform::mail.registration_receipt.intro', ['plan' => $this->planName, 'term' => $this->term]))
            ->line(__('platform::mail.registration_receipt.amount', ['amount' => $this->amount]))
            ->line(__('platform::mail.registration_receipt.reference', ['reference' => $this->reference]))
            ->line(__('platform::mail.registration_receipt.next'));
    }
}
