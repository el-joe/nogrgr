<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
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
class CustomerTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof Customer && $notifiable->locale !== null ? $notifiable->locale : app()->getLocale();
        $key = 'support::customer_tickets.notifications.'.$this->eventName;
        $mail = (new MailMessage)
            ->subject((string) __($key.'.title', $this->params, $locale))
            ->greeting((string) __('support::customer_tickets.mail.greeting', ['name' => (string) ($notifiable->name ?? '')], $locale))
            ->line((string) __($key.'.body', $this->params, $locale));

        if (($this->params['excerpt'] ?? '') !== '' && $this->eventName === 'ticket_replied') {
            $mail->line('“'.$this->params['excerpt'].'”');
        }

        return $this->ticketUrl !== null
            ? $mail->action((string) __('support::customer_tickets.mail.cta', [], $locale), $this->ticketUrl)
            : $mail;
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
