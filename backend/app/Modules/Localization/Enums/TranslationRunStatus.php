<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Paid store translation run (decision #45): queued after the language is paid,
 * translating while the P10 run works (progress %), completed (language switched on)
 * or failed (the vendor can retry).
 */
enum TranslationRunStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Translating = 'translating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Translating => 'brand',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Translating;
    }
}
