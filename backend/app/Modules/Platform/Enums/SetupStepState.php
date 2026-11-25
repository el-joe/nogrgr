<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Status chip of a setup checklist row (Figma 82:100227: Done / Next / Skip for now / Optional).
 */
enum SetupStepState: string
{
    use HasEnumHelpers;

    case Done = 'done';
    case Skipped = 'skipped';
    case Next = 'next';
    case Pending = 'pending';
    case Optional = 'optional';

    public function isComplete(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }

    /**
     * x-ui::badge tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Done => 'success',
            self::Skipped => 'warning',
            self::Next => 'brand',
            self::Pending, self::Optional => 'neutral',
        };
    }
}
