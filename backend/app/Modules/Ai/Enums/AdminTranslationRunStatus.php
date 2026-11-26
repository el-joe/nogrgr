<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Admin AI translation run: queued → translating → completed | failed, or stopped by
 * an admin (cancelled). Failed and cancelled runs can be retried.
 */
enum AdminTranslationRunStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Translating = 'translating';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Translating => 'brand',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Translating;
    }

    public function canRetry(): bool
    {
        return $this === self::Failed || $this === self::Cancelled;
    }
}
