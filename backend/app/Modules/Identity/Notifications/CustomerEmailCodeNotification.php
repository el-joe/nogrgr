<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Storefront e-mail confirmation code (mail only; a code never goes to the database or a
 * broadcast channel). Texts come from the store's static texts (group "auth").
 */
class CustomerEmailCodeNotification extends Notification
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
            ->subject(__st('auth.mail_subject', ['code' => $this->code]))
            ->line(__st('auth.mail_intro', ['store' => $this->storeName]))
            ->line('**'.$this->code.'**')
            ->line(__st('auth.mail_expiry', ['minutes' => $this->expiresInMinutes]))
            ->line(__st('auth.mail_ignore'));
    }
}
