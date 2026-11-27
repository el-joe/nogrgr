<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link (mail only: a reset token never goes to the database or a
 * broadcast channel). The URL is built by the notifiable's model on the host the
 * request came from (central panel or the store's own domain).
 */
class ResetPasswordNotification extends Notification implements ShouldQueue, TemplatedMailNotification
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
        return [EmailTemplateEvent::AdminPasswordReset, EmailTemplateEvent::AffiliatePasswordReset, EmailTemplateEvent::VendorPasswordReset, EmailTemplateEvent::CustomerPasswordReset];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return match (true) {
            $notifiable instanceof Admin => EmailTemplateEvent::AdminPasswordReset,
            $notifiable instanceof Affiliate => EmailTemplateEvent::AffiliatePasswordReset,
            $notifiable instanceof Customer => EmailTemplateEvent::CustomerPasswordReset,
            default => EmailTemplateEvent::VendorPasswordReset,
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = (string) ($notifiable->name ?? '');

        return $this->templatedMail($this->mailTemplate($notifiable), [
            'recipient.name' => $name,
            'customer.first_name' => (string) strtok($name, ' '),
            'customer.name' => $name,
            'reset.url' => $this->url,
            'reset.expires_minutes' => (string) $this->expiresInMinutes,
        ], $this->recipientLocale($notifiable));
    }
}
