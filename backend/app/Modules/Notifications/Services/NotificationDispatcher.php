<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Notifications\PanelNotification;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Single entry point for domain-event listeners: sends a PanelNotification to one or
 * more central users. Channels come from each user's preferences (PanelNotification::via()).
 */
class NotificationDispatcher extends BaseService
{
    /**
     * @param  Model|iterable<Model>  $notifiables
     */
    public function send(Model|iterable $notifiables, PanelNotification $notification): void
    {
        Notification::send($notifiables instanceof Model ? [$notifiables] : $notifiables, $notification);
    }
}
