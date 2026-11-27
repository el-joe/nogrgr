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
 * E-mails the signed "complete your registration" link (sign-up, admin "resend link").
 */
class RegistrationLinkNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::VendorRegistrationLink];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorRegistrationLink;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorRegistrationLink, [
            'vendor.name' => (string) ($notifiable->name ?? ''),
            'registration.url' => $this->url,
            'registration.expires_hours' => (string) $this->hours,
        ]);
    }
}
