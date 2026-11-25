<?php

namespace App\Modules\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the store owner once provisioning finished: store name, store address and
 * the vendor panel sign-in link.
 */
class VendorWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storeName,
        public readonly string $storeUrl,
        public readonly string $loginUrl,
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
        return (new MailMessage)
            ->subject(__('platform::mail.welcome.subject', ['store' => $this->storeName]))
            ->greeting(__('platform::mail.welcome.greeting'))
            ->line(__('platform::mail.welcome.intro', ['store' => $this->storeName]))
            ->line(__('platform::mail.welcome.address', ['url' => $this->storeUrl]))
            ->action(__('platform::mail.welcome.action'), $this->loginUrl)
            ->line(__('platform::mail.welcome.outro'));
    }
}
