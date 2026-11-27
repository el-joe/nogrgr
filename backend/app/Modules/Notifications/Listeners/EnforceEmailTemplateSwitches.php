<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Admin / Vendor › Email templates enable switch (decision #118): cancels the mail
 * channel of a templated notification whose template is disabled (for the store, the
 * store's customised copy decides). Database / Reverb / push are not affected.
 */
class EnforceEmailTemplateSwitches
{
    public function __construct(private readonly EmailTemplateRenderer $renderer) {}

    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel !== 'mail' || ! $event->notification instanceof TemplatedMailNotification) {
            return null;
        }

        return $this->renderer->isEnabled($event->notification->mailTemplate($event->notifiable)) ? null : false;
    }
}
