<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Custom domain lifecycle: pending → connected (host added to the tenancy `domains`
 * table) → removed (host dropped). A removed request can be re-opened (pending).
 */
enum DomainRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Connected = 'connected';
    case Removed = 'removed';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Connected, self::Removed],
            self::Connected => [self::Removed],
            self::Removed => [self::Pending],
        }, true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Connected => 'success',
            self::Removed => 'neutral',
        };
    }
}
