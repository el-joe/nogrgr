<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * trial → active | expired · active → past_due | cancelled | expired ·
 * past_due → active (paid) | cancelled | expired · cancelled → expired.
 * expired is final (a new subscription is created instead).
 */
enum SubscriptionStatus: string
{
    use HasEnumHelpers;

    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Still counts as the store's current subscription.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Trial, self::Active, self::PastDue];
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Trial => [self::Active, self::Cancelled, self::Expired],
            self::Active => [self::Active, self::PastDue, self::Cancelled, self::Expired],
            self::PastDue => [self::Active, self::Cancelled, self::Expired],
            self::Cancelled => [self::Expired],
            self::Expired => [],
        }, true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Cancelled => 'danger',
            self::Expired => 'neutral',
        };
    }
}
