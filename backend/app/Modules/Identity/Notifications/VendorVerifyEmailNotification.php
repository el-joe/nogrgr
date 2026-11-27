<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Vendor staff e-mail confirmation link (mail only). The signed URL points at the
 * store's own host (VEN-004 result page).
 */
class VendorVerifyEmailNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::VendorVerifyEmail];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorVerifyEmail;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorVerifyEmail, [
            'recipient.name' => (string) ($notifiable->name ?? ''),
            'verify.url' => $this->url,
            'verify.expires_minutes' => (string) $this->expiresInMinutes,
        ], $this->recipientLocale($notifiable));
    }
}
