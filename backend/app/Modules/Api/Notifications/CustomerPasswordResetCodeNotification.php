<?php

namespace App\Modules\Api\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mobile "forgot password" (APP-022 / APP-023): the 6-digit reset code by e-mail, in the
 * store's static texts (`mobile.reset.*`).
 */
class CustomerPasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
        public readonly string $storeName,
    ) {}

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
            ->subject(__st('mobile.reset.mail_subject', ['code' => $this->code]))
            ->line(__st('mobile.reset.mail_intro', ['store' => $this->storeName]))
            ->line('**'.$this->code.'**')
            ->line(__st('mobile.reset.mail_expiry', ['minutes' => $this->expiresInMinutes]))
            ->line(__st('mobile.reset.mail_ignore'));
    }
}
