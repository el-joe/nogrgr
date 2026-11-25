<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * External catalog import run state (ADM-013). Queued → running ⇄ paused → completed /
 * failed / cancelled.
 */
enum ImportStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('catalog::imports.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Running, self::Paused => 'warning',
            self::Completed => 'success',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    /**
     * Still holds the driver: a new run cannot start while one is active.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running, self::Paused], true);
    }

    public function canPause(): bool
    {
        return $this === self::Running;
    }

    public function canResume(): bool
    {
        return $this === self::Paused;
    }

    public function canCancel(): bool
    {
        return $this->isActive();
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value, self::Paused->value];
    }
}
