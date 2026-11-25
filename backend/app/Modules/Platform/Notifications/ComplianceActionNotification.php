<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Platform\Enums\ComplianceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the store owner the outcome of the compliance review, with the admin's note.
 */
class ComplianceActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storeName,
        public readonly string $status,
        public readonly ?string $note,
    ) {
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
        $status = ComplianceStatus::from($this->status);
        $mail = (new MailMessage)
            ->subject(__('platform::mail.compliance.subject_'.$status->value, ['store' => $this->storeName]))
            ->greeting(__('platform::mail.compliance.greeting'))
            ->line(__('platform::mail.compliance.intro_'.$status->value, ['store' => $this->storeName]));

        if ($this->note !== null && $this->note !== '') {
            $mail->line(__('platform::mail.compliance.note_heading'))->line($this->note);
        }

        return $mail->line(__('platform::mail.compliance.outro'));
    }
}
