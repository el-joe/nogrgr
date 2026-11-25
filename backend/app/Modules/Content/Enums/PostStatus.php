<?php

namespace App\Modules\Content\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PostStatus: string
{
    use HasEnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function tone(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Draft => 'warning',
            self::Archived => 'neutral',
        };
    }
}
