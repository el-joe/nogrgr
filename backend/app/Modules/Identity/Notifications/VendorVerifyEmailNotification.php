<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Vendor staff e-mail confirmation link (mail only). The signed URL points at the
 * store's own host (VEN-004 result page).
 */
class VendorVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $expiresInMinutes,
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::vendor_auth.verify.mail_subject'))
            ->line(__('identity::vendor_auth.verify.mail_intro'))
            ->action(__('identity::vendor_auth.verify.mail_action'), $this->url)
            ->line(__('identity::vendor_auth.verify.mail_expiry', ['count' => $this->expiresInMinutes]));
    }
}
