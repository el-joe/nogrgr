<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Jobs\SendCustomerPush;
use App\Modules\Notifications\Support\Push\FcmClient;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Every customer inbox row (database channel) is also pushed to the customer's mobile
 * apps (P11) — one place for all customer notifications instead of a channel per class.
 */
class QueueCustomerPush
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof Customer || ! $this->fcm->isConfigured()) {
            return;
        }

        $record = $event->response;

        if (! $record instanceof DatabaseNotification || ! is_array($record->data)) {
            return;
        }

        SendCustomerPush::dispatch($event->notifiable->id, (string) $record->id, $record->data)->afterCommit();
    }
}
