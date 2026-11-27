<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Storefront e-mail confirmation code (mail only; a code never goes to the database or a
 * broadcast channel). Texts come from the store's static texts (group "auth").
 */
class CustomerEmailCodeNotification extends Notification implements TemplatedMailNotification
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
        return [EmailTemplateEvent::CustomerEmailCode];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerEmailCode;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::CustomerEmailCode, [
            'customer.first_name' => (string) strtok((string) ($notifiable->name ?? ''), ' '),
            'customer.name' => (string) ($notifiable->name ?? ''),
            'store.name' => $this->storeName,
            'code.value' => $this->code,
            'code.expires_minutes' => (string) $this->expiresInMinutes,
        ], $this->recipientLocale($notifiable));
    }
}
