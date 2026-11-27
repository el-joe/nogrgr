<?php

namespace App\Modules\Affiliates\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Partner sign-up e-mail confirmation link (AFF-006, mail only).
 */
class AffiliateVerifyEmailNotification extends Notification implements ShouldQueue, TemplatedMailNotification
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
        return [EmailTemplateEvent::AffiliateVerifyEmail];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::AffiliateVerifyEmail;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::AffiliateVerifyEmail, [
            'recipient.name' => (string) ($notifiable->name ?? ''),
            'verify.url' => $this->url,
            'verify.expires_minutes' => (string) $this->expiresInMinutes,
        ], $this->recipientLocale($notifiable));
    }
}
