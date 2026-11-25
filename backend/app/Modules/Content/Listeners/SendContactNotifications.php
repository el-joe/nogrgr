<?php

namespace App\Modules\Content\Listeners;

use App\Modules\Content\Events\ContactMessageReceived;
use App\Modules\Content\Notifications\ContactMessageMail;
use App\Modules\Content\Notifications\ContactMessageNotification;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Modules\Notifications\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * Contact form → admins allowed to read contact messages (database + mail + Reverb per
 * their preferences) and the full message to the support inbox from General settings.
 */
class SendContactNotifications
{
    public const PERMISSION = 'content.contact.view';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly GeneralSettingsService $settings,
    ) {}

    public function handle(ContactMessageReceived $event): void
    {
        $message = $event->message;
        $admins = $this->admins->activeWithPermission(self::PERMISSION);

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, new ContactMessageNotification($message->name, $message->email, $message->subject));
        }

        $inbox = trim((string) $this->settings->current()['support_email']);

        if ($inbox !== '' && filter_var($inbox, FILTER_VALIDATE_EMAIL) !== false) {
            Notification::route('mail', $inbox)->notify(new ContactMessageMail($message));
        }
    }
}
