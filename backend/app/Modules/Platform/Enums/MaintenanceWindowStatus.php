<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Derived state of a maintenance window at a moment (not stored).
 */
enum MaintenanceWindowStatus: string
{
    use HasEnumHelpers;

    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Active => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Scheduled, self::Active], true);
    }
}
