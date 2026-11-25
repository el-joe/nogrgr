<?php

namespace App\Modules\Commerce\Notifications;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Order e-mail + account notification for the customer (order confirmation, shipped,
 * delivered, cancelled). The mail uses the admin's active template of the event
 * (EmailTemplateEvent, Admin › Email templates) and falls back to the built-in
 * commerce::notifications.customer.<event> texts. E-mail follows the customer's
 * "order updates" / "shipping updates" e-mail preference; the account inbox always gets it.
 */
class OrderCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $variables  template variables (EmailTemplateEvent::variables())
     */
    public function __construct(
        public readonly EmailTemplateEvent $template,
        public readonly string $eventName,
        public readonly array $variables,
        public readonly string $orderUuid,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $type = in_array($this->template, [EmailTemplateEvent::OrderShipped, EmailTemplateEvent::OrderDelivered], true)
            ? CustomerNotificationType::ShippingUpdates
            : CustomerNotificationType::OrderUpdates;

        if ($notifiable instanceof Customer && filled($notifiable->email)
            && app(CustomerNotificationPreferences::class)->allows($notifiable, $type, CustomerNotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage|TemplatedMail
    {
        $locale = $notifiable instanceof Customer && $notifiable->locale !== null ? $notifiable->locale : app()->getLocale();
        $rendered = app(EmailTemplateRenderer::class)->renderEvent($this->template, $locale, $this->variables);

        if ($rendered !== null) {
            return (new TemplatedMail($rendered))->to((string) $notifiable->routeNotificationFor('mail', $this));
        }

        $params = [];

        foreach ($this->variables as $key => $value) {
            $params[str_replace('.', '_', $key)] = $value;
        }

        $mail = (new MailMessage)
            ->subject((string) __('commerce::notifications.customer.'.$this->eventName.'.subject', $params, $locale))
            ->greeting((string) __('commerce::notifications.customer.greeting', $params, $locale))
            ->line((string) __('commerce::notifications.customer.'.$this->eventName.'.body', $params, $locale));

        return ($this->variables['order.url'] ?? '') !== ''
            ? $mail->action((string) __('commerce::notifications.customer.view_order', [], $locale), $this->variables['order.url'])
            : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->eventName,
            'order_uuid' => $this->orderUuid,
            'title_key' => 'commerce::notifications.customer.'.$this->eventName.'.subject',
            'body_key' => 'commerce::notifications.customer.'.$this->eventName.'.body',
            'params' => $this->variables,
        ];
    }
}
