<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Engagement\Enums\ReviewStatus;
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
 * The store moderated the customer's review (decision #124): account inbox + app push
 * (QueueCustomerPush) for "published" and "not published"; a published review also
 * e-mails `customer.review_published` when the order-updates e-mail preference allows it.
 */
class ReviewModeratedNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    /**
     * @param  array<string, string>  $variables  store.*, product.name, product.url, review.rating
     */
    public function __construct(
        public readonly int $reviewId,
        public readonly ReviewStatus $status,
        public readonly array $variables,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->status === ReviewStatus::Approved && $notifiable instanceof Customer && filled($notifiable->email)
            && app(CustomerNotificationPreferences::class)->allows($notifiable, CustomerNotificationType::OrderUpdates, CustomerNotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CustomerReviewPublished];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CustomerReviewPublished;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof Customer ? (string) $notifiable->name : '';

        return $this->templatedMail($this->mailTemplate($notifiable), $this->variables + [
            'customer.name' => $name,
            'customer.first_name' => (string) strtok($name, ' '),
        ], $this->recipientLocale($notifiable));
    }

    public function eventName(): string
    {
        return $this->status === ReviewStatus::Approved ? 'review_published' : 'review_rejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->eventName(),
            'review_id' => $this->reviewId,
            'url' => $this->variables['product.url'] ?? null,
            'title_key' => 'engagement::notifications.customer.'.$this->eventName().'.title',
            'body_key' => 'engagement::notifications.customer.'.$this->eventName().'.body',
            'params' => ['product' => $this->variables['product.name'] ?? '', 'store' => $this->variables['store.name'] ?? ''],
        ];
    }
}
