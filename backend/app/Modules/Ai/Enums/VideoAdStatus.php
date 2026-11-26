<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Lifecycle of a generated video ad: queued → writing the script → creating the scenes →
 * recording the voice-over → assembling the video → ready | failed.
 */
enum VideoAdStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Scripting = 'scripting';
    case Visuals = 'visuals';
    case Voice = 'voice';
    case Rendering = 'rendering';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Ready, self::Failed], true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isActive()));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ready => 'success',
            self::Failed => 'danger',
            default => 'info',
        };
    }
}
