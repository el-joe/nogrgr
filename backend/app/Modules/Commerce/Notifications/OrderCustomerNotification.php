<?php

namespace App\Modules\Commerce\Notifications;

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
 * Order e-mail + account notification for the customer (order confirmation, shipped,
 * delivered, cancelled, shipping steps, refunds, returns). The mail renders the registry template of the event
 * (order.shipping_update for shipping steps, order.return_update for returns) with the
 * commerce::notifications.customer.<event> texts as alert title / body. E-mail follows the customer's
 * "order updates" / "shipping updates" e-mail preference; the account inbox always gets it.
 */
class OrderCustomerNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    /**
     * @param  array<string, string>  $variables  template variables (EmailTemplateEvent::variables())
     */
    public function __construct(
        public readonly ?EmailTemplateEvent $template,
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
        $type = match (true) {
            str_starts_with($this->eventName, 'return_') => CustomerNotificationType::Returns,
            in_array($this->template, [EmailTemplateEvent::OrderShipped, EmailTemplateEvent::OrderDelivered], true) || str_starts_with($this->eventName, 'shipping_') => CustomerNotificationType::ShippingUpdates,
            default => CustomerNotificationType::OrderUpdates,
        };

        if ($notifiable instanceof Customer && filled($notifiable->email)
            && app(CustomerNotificationPreferences::class)->allows($notifiable, $type, CustomerNotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::OrderConfirmation, EmailTemplateEvent::OrderShipped, EmailTemplateEvent::OrderDelivered, EmailTemplateEvent::OrderCancelled, EmailTemplateEvent::OrderRefundProcessed, EmailTemplateEvent::OrderShippingUpdate, EmailTemplateEvent::OrderReturnUpdate, EmailTemplateEvent::OrderUpdate];
    }

    /**
     * Shipping steps (shipping_*) use order.shipping_update, return events (return_*)
     * order.return_update (decision #124); anything else without a template order.update.
     */
    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return $this->template ?? match (true) {
            str_starts_with($this->eventName, 'shipping_') => EmailTemplateEvent::OrderShippingUpdate,
            str_starts_with($this->eventName, 'return_') => EmailTemplateEvent::OrderReturnUpdate,
            default => EmailTemplateEvent::OrderUpdate,
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->recipientLocale($notifiable);
        $params = [];

        foreach ($this->variables as $key => $value) {
            $params[str_replace('.', '_', $key)] = $value;
        }

        return $this->templatedMail($this->mailTemplate($notifiable), $this->variables + [
            'alert.title' => (string) __('commerce::notifications.customer.'.$this->eventName.'.subject', $params, $locale),
            'alert.body' => (string) __('commerce::notifications.customer.'.$this->eventName.'.body', $params, $locale),
        ], $locale);
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
