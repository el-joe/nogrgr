<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Newsletter subscriber state (VEN-026): `unsubscribed_at` empty = subscribed.
 */
enum SubscriberStatus: string
{
    use HasEnumHelpers;

    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return __('engagement::subscribers.statuses.'.$this->value);
    }
}
