<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reset link for the central store login (WEB-023), sent on demand to the e-mail.
 */
class StoreLoginPasswordResetNotification extends Notification implements TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::VendorPasswordReset];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorPasswordReset;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorPasswordReset, [
            'recipient.name' => (string) ($notifiable->name ?? ''),
            'reset.url' => route('central.password.reset', ['token' => $this->token, 'email' => $this->email, 'lang' => $this->mailLocale]),
            'reset.expires_minutes' => (string) self::EXPIRE_MINUTES,
        ], $this->mailLocale);
    }

    public const EXPIRE_MINUTES = 60;
}
