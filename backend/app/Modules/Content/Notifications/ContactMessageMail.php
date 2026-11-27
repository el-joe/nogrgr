<?php

namespace App\Modules\Content\Notifications;

use App\Modules\Content\Models\ContactMessage;
use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Full contact-form message mailed to the platform support inbox (Admin › Settings ›
 * General › support e-mail), reply-to the visitor (MAIL-041).
 */
class ContactMessageMail extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct(public readonly ContactMessage $contact)
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
        return [EmailTemplateEvent::AdminContactMessage];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::AdminContactMessage;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::AdminContactMessage, [
            'contact.name' => (string) $this->contact->name,
            'contact.email' => (string) $this->contact->email,
            'contact.phone' => (string) ($this->contact->phone ?? ''),
            'contact.subject' => (string) $this->contact->subject,
            'contact.message' => (string) $this->contact->message,
        ])->replyTo($this->contact->email, $this->contact->name);
    }
}
