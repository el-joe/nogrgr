<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum RequestPriority: string
{
    use HasEnumHelpers;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function tone(): string
    {
        return match ($this) {
            self::Low, self::Normal => 'neutral',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
