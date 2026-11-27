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
 * Decision #124: the DNS records of the store owner's custom domain went missing;
 * `vendor.domain_dns_failed` template, sent to the store's owner e-mail.
 */
class DomainDnsFailedNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct(
        public readonly string $ownerName,
        public readonly string $storeName,
        public readonly string $domain,
        public readonly string $loginUrl,
    ) {
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
        return [EmailTemplateEvent::VendorDomainDnsFailed];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorDomainDnsFailed;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorDomainDnsFailed, [
            'vendor.name' => $this->ownerName,
            'store.name' => $this->storeName,
            'domain.name' => $this->domain,
            'login.url' => $this->loginUrl,
        ]);
    }
}
