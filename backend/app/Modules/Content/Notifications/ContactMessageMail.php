<?php

namespace App\Modules\Content\Notifications;

use App\Modules\Content\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Full contact-form message mailed to the platform support inbox (Admin › Settings ›
 * General › support e-mail), reply-to the visitor (MAIL-041).
 */
class ContactMessageMail extends Notification implements ShouldQueue
{
    use Queueable;

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

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('content::notifications.contact.mail_subject', ['subject' => $this->contact->subject]))
            ->replyTo($this->contact->email, $this->contact->name)
            ->line(__('content::notifications.contact.from', ['name' => $this->contact->name, 'email' => $this->contact->email]));

        if ($this->contact->phone !== null) {
            $mail->line(__('content::notifications.contact.phone', ['phone' => $this->contact->phone]));
        }

        return $mail
            ->line(__('content::notifications.contact.subject_line', ['subject' => $this->contact->subject]))
            ->line($this->contact->message);
    }
}
