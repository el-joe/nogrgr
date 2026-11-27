<?php

namespace App\Modules\Inventory\Notifications;

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
 * "Back in stock" notice (decision #124, template `customer.back_in_stock`): a signed-in
 * subscriber gets the account inbox (→ app push, QueueCustomerPush) and the e-mail when
 * the "back in stock" e-mail preference allows it; a guest subscriber the e-mail only.
 */
class BackInStockNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    /**
     * @param  array<string, string>  $variables  store.name, store.url, product.name, product.url
     */
    public function __construct(
        public readonly int $storeProductId,
        public readonly array $variables,
        public readonly ?string $mailLocale = null, // not `$locale`: Notification::$locale is untyped and mutable
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof Customer) {
            return ['mail'];
        }

        $channels = ['database'];

        if (filled($notifiable->email) && app(CustomerNotificationPreferences::class)->allows($notifiable, CustomerNotificationType::BackInStock, CustomerNotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CustomerBackInStock];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerBackInStock;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof Customer ? $this->recipientLocale($notifiable) : ($this->mailLocale ?: app()->getLocale());
        $name = $notifiable instanceof Customer ? (string) $notifiable->name : '';

        return $this->templatedMail($this->mailTemplate($notifiable), $this->variables + [
            'customer.name' => $name,
            'customer.first_name' => (string) strtok($name, ' '),
        ], $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'back_in_stock',
            'store_product_id' => $this->storeProductId,
            'url' => $this->variables['product.url'] ?? null,
            'title_key' => 'inventory::alerts.back_in_stock.title',
            'body_key' => 'inventory::alerts.back_in_stock.body',
            'params' => ['product' => $this->variables['product.name'] ?? '', 'store' => $this->variables['store.name'] ?? ''],
        ];
    }
}
