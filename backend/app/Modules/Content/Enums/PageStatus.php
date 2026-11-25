<?php

namespace App\Modules\Content\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PageStatus: string
{
    use HasEnumHelpers;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'warning',
            self::Archived => 'neutral',
        };
    }
}
