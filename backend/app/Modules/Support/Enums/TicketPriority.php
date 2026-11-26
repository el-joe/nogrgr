<?php

namespace App\Modules\Support\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Ticket priority with the platform's response target (SLA hours from the requester's
 * latest message).
 */
enum TicketPriority: string
{
    use HasEnumHelpers;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function slaHours(): int
    {
        return match ($this) {
            self::Low => 48,
            self::Normal => 24,
            self::High => 8,
            self::Urgent => 4,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'neutral',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
