<?php

namespace App\Modules\Content\Notifications;

use App\Modules\Content\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Double opt-in e-mail for the footer newsletter (WEB-015): confirm + unsubscribe links,
 * in the language the visitor subscribed in.
 */
class NewsletterConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(NewsletterSubscriber $notifiable): MailMessage
    {
        $locale = $notifiable->locale;

        return (new MailMessage)
            ->subject(__('content::notifications.newsletter.subject', ['name' => config('app.name')], $locale))
            ->greeting(__('content::notifications.newsletter.greeting', [], $locale))
            ->line(__('content::notifications.newsletter.body', ['name' => config('app.name')], $locale))
            ->action(__('content::notifications.newsletter.confirm', [], $locale), route('central.newsletter.confirm', ['token' => $notifiable->token, 'lang' => $locale]))
            ->line(__('content::notifications.newsletter.ignore', [], $locale))
            ->line(__('content::notifications.newsletter.unsubscribe', ['url' => route('central.newsletter.unsubscribe', ['token' => $notifiable->token, 'lang' => $locale])], $locale));
    }
}
