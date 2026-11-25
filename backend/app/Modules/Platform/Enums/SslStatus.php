<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum SslStatus: string
{
    use HasEnumHelpers;

    case Unknown = 'unknown';
    case Valid = 'valid';
    case Invalid = 'invalid';

    public function tone(): string
    {
        return match ($this) {
            self::Unknown => 'neutral',
            self::Valid => 'success',
            self::Invalid => 'danger',
        };
    }
}
