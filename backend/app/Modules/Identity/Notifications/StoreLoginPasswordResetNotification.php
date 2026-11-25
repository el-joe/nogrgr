<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reset link for the central store login (WEB-023), sent on demand to the e-mail.
 */
class StoreLoginPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly string $mailLocale,
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
        $url = route('central.password.reset', ['token' => $this->token, 'email' => $this->email, 'lang' => $this->mailLocale]);

        return (new MailMessage)
            ->subject(__('identity::store_login.reset_mail.subject', ['name' => config('app.name')], $this->mailLocale))
            ->line(__('identity::store_login.reset_mail.intro', [], $this->mailLocale))
            ->action(__('identity::store_login.reset_mail.action', [], $this->mailLocale), $url)
            ->line(__('identity::store_login.reset_mail.expire', ['count' => StoreLoginPasswordResetNotification::EXPIRE_MINUTES], $this->mailLocale))
            ->line(__('identity::store_login.reset_mail.ignore', [], $this->mailLocale));
    }

    public const EXPIRE_MINUTES = 60;
}
