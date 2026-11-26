<?php

namespace App\Modules\Affiliates\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Partner sign-up e-mail confirmation link (AFF-006, mail only).
 */
class AffiliateVerifyEmailNotification extends Notification implements ShouldQueue
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
            ->subject(__('affiliates::panel.verify.mail_subject'))
            ->line(__('affiliates::panel.verify.mail_intro'))
            ->action(__('affiliates::panel.verify.mail_action'), $this->url)
            ->line(__('affiliates::panel.verify.mail_expiry', ['count' => $this->expiresInMinutes]));
    }
}
