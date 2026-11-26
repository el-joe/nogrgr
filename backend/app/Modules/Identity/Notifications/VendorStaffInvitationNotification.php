<?php

namespace App\Modules\Identity\Notifications;

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
class VendorStaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::staff.invite_mail.subject', ['store' => $this->storeName]))
            ->line(__('identity::staff.invite_mail.intro', ['inviter' => $this->inviterName, 'store' => $this->storeName, 'role' => $this->roleLabel]))
            ->action(__('identity::staff.invite_mail.action'), $this->url)
            ->line(__('identity::staff.invite_mail.expiry', ['count' => $this->expiresInMinutes]))
            ->line(__('identity::staff.invite_mail.ignore'));
    }
}
