<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum CacheRunStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Done = 'done';
    case Failed = 'failed';

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }
}
