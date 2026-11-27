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
 * MAIL-042 customer welcome (decision #124): e-mail to a shopper who just created an
 * account in a store, from the store-scoped `customer.welcome` template (store layout).
 */
class CustomerWelcomeNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct(public readonly string $storeName, public readonly string $storeUrl)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CustomerWelcome];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerWelcome;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = (string) ($notifiable->name ?? '');

        return $this->templatedMail(EmailTemplateEvent::CustomerWelcome, [
            'customer.first_name' => (string) strtok($name, ' '),
            'customer.name' => $name,
            'store.name' => $this->storeName,
            'store.url' => $this->storeUrl,
        ], $this->recipientLocale($notifiable));
    }
}
