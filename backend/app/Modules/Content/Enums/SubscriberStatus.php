<?php

namespace App\Modules\Content\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum SubscriberStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    public function tone(): string
    {
        return match ($this) {
            self::Subscribed => 'success',
            self::Pending => 'warning',
            self::Unsubscribed => 'neutral',
        };
    }
}
