<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Notifications\Concerns\RendersMailTemplate;
use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Store team invitation (VEN-076a): mail only, because it carries a one-time
 * set-password token (never stored in an inbox or broadcast). The link opens the store
 * host's invitation (welcome / set-password) page; setting the password activates the member
 * (ActivateInvitedVendorStaff).
 */
class VendorStaffInvitationNotification extends Notification implements ShouldQueue, TemplatedMailNotification
{
    use Queueable;
    use RendersMailTemplate;

    public function __construct(
        public readonly string $storeName,
        public readonly string $inviterName,
        public readonly string $roleLabel,
        public readonly string $url,
        public readonly int $expiresInMinutes,
    ) {
        $this->onQueue('mail');
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
        return [EmailTemplateEvent::VendorStaffInvitation];
    }

    public function mailTemplate(object $notifiable): EmailTemplateEvent
    {
        return EmailTemplateEvent::VendorStaffInvitation;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->templatedMail(EmailTemplateEvent::VendorStaffInvitation, [
            'store.name' => $this->storeName,
            'inviter.name' => $this->inviterName,
            'role.name' => $this->roleLabel,
            'invitation.url' => $this->url,
            'invitation.expires_minutes' => (string) $this->expiresInMinutes,
        ]);
    }
}
