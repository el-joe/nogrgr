<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Delivery channels a panel user can switch per category: in-app (database inbox +
 * bell), email (mail) and push (live Reverb broadcast to the open panel).
 */
enum NotificationChannel: string
{
    use HasEnumHelpers;

    case InApp = 'in_app';
    case Email = 'email';
    case Push = 'push';

    /**
     * Laravel notification channel name.
     */
    public function driver(): string
    {
        return match ($this) {
            self::InApp => 'database',
            self::Email => 'mail',
            self::Push => 'broadcast',
        };
    }

    public static function fromDriver(string $driver): ?self
    {
        foreach (self::cases() as $channel) {
            if ($channel->driver() === $driver) {
                return $channel;
            }
        }

        return null;
    }
}
