<?php

namespace App\Modules\Content\Notifications;

use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Double opt-in e-mail for the footer newsletter (WEB-015): confirm + unsubscribe links,
 * in the language the visitor subscribed in.
 */
class NewsletterConfirmationNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::CentralNewsletterConfirmation];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::CentralNewsletterConfirmation;
    }

    public function toMail(NewsletterSubscriber $notifiable): MailMessage
    {
        $locale = (string) $notifiable->locale;

        return $this->templatedMail(EmailTemplateEvent::CentralNewsletterConfirmation, [
            'confirm.url' => route('central.newsletter.confirm', ['token' => $notifiable->token, 'lang' => $locale]),
            'unsubscribe.url' => route('central.newsletter.unsubscribe', ['token' => $notifiable->token, 'lang' => $locale]),
        ], $locale);
    }
}
