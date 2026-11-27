<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Store → customer ticket update (store replied, ticket resolved / closed / reopened):
 * account inbox (database; the mobile push follows through QueueCustomerPush) and e-mail
 * when the customer's "Support" e-mail switch is on. Texts:
 * support::customer_tickets.notifications.<event> in the customer's language.
 */
class CustomerTicketNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public readonly string $eventName,
        public readonly int $ticketId,
        public readonly array $params,
        public readonly ?string $ticketUrl = null,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof Customer && filled($notifiable->email)
            && app(CustomerNotificationPreferences::class)->allows($notifiable, CustomerNotificationType::Support, CustomerNotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CustomerTicketUpdate];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerTicketUpdate;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->recipientLocale($notifiable);
        $key = 'support::customer_tickets.notifications.'.$this->eventName;
        $name = (string) ($notifiable->name ?? '');

        return $this->templatedMail(EmailTemplateEvent::CustomerTicketUpdate, [
            'customer.first_name' => (string) strtok($name, ' '),
            'customer.name' => $name,
            'ticket.reference' => (string) ($this->params['reference'] ?? ''),
            'ticket.subject' => (string) ($this->params['subject'] ?? ''),
            'alert.title' => (string) __($key.'.title', $this->params, $locale),
            'alert.body' => (string) __($key.'.body', $this->params, $locale),
            'reply.excerpt' => $this->eventName === 'ticket_replied' ? (string) ($this->params['excerpt'] ?? '') : '',
            'ticket.url' => (string) ($this->ticketUrl ?? ''),
        ], $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->eventName,
            'ticket_id' => $this->ticketId,
            'title_key' => 'support::customer_tickets.notifications.'.$this->eventName.'.title',
            'body_key' => 'support::customer_tickets.notifications.'.$this->eventName.'.body',
            'params' => $this->params,
            'url' => $this->ticketUrl,
        ];
    }
}
