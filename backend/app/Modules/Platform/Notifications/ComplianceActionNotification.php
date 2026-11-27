<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Platform\Enums\ComplianceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the store owner the outcome of the compliance review, with the admin's note.
 */
class ComplianceActionNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

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

    /**
     * @return list<EmailTemplateEvent>
     */
    public static function mailTemplates(): array
    {
        return [EmailTemplateEvent::VendorComplianceDecision];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorComplianceDecision;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = ComplianceStatus::from($this->status);

        return $this->templatedMail(EmailTemplateEvent::VendorComplianceDecision, [
            'store.name' => $this->storeName,
            'compliance.status' => $status->label(),
            'compliance.message' => (string) __('platform::mail.compliance.intro_'.$status->value, ['store' => $this->storeName]),
            'compliance.note' => (string) ($this->note ?? ''),
        ]);
    }
}
