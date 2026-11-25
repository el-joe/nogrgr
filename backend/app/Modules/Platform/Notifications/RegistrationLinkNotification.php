<?php

namespace App\Modules\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mails the signed "complete your registration" link (sign-up, admin "resend link").
 */
class RegistrationLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $url, public readonly int $hours)
    {
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
            ->subject(__('platform::mail.registration_link.subject', ['app' => config('app.name')]))
            ->greeting(__('platform::mail.registration_link.greeting'))
            ->line(__('platform::mail.registration_link.intro'))
            ->action(__('platform::mail.registration_link.action'), $this->url)
            ->line(__('platform::mail.registration_link.expiry', ['hours' => $this->hours]))
            ->line(__('platform::mail.registration_link.ignore'));
    }
}
