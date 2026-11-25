<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Outcome of one log line of an import run. `warning` is informational (an image that
 * could not be downloaded, stock without a branch) and does not count as an error.
 */
enum ImportLogResult: string
{
    use HasEnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return __('catalog::imports.results.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Skipped, self::Warning => 'warning',
            self::Error => 'danger',
        };
    }

    /**
     * Run counter incremented by a line with this result.
     */
    public function counter(): ?string
    {
        return match ($this) {
            self::Created => 'created_count',
            self::Updated => 'updated_count',
            self::Skipped => 'skipped_count',
            self::Error => 'error_count',
            self::Warning => null,
        };
    }
}
