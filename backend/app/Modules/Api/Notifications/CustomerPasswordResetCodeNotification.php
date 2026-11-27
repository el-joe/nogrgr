<?php

namespace App\Modules\Api\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mobile "forgot password" (APP-022 / APP-023): the 6-digit reset code by e-mail, in the
 * store's static texts (`mobile.reset.*`).
 */
class CustomerPasswordResetCodeNotification extends Notification implements TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CustomerPasswordResetCode];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerPasswordResetCode;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::CustomerPasswordResetCode, [
            'customer.first_name' => (string) strtok((string) ($notifiable->name ?? ''), ' '),
            'customer.name' => (string) ($notifiable->name ?? ''),
            'store.name' => $this->storeName,
            'code.value' => $this->code,
            'code.expires_minutes' => (string) $this->expiresInMinutes,
        ], $this->recipientLocale($notifiable));
    }
}
