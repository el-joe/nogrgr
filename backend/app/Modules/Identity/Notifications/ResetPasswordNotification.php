<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link (mail only: a reset token never goes to the database or a
 * broadcast channel). The URL is built by the notifiable's model on the host the
 * request came from (central panel or the store's own domain).
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
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
            ->subject(__('identity::auth.reset.mail_subject'))
            ->line(__('identity::auth.reset.mail_intro'))
            ->action(__('identity::auth.reset.mail_action'), $this->url)
            ->line(__('identity::auth.reset.mail_expiry', ['count' => $this->expiresInMinutes]))
            ->line(__('identity::auth.reset.mail_ignore'));
    }
}
