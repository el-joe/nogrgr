<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum DnsCheckStatus: string
{
    use HasEnumHelpers;

    case Unchecked = 'unchecked';
    case Ok = 'ok';
    case Missing = 'missing';
    case Error = 'error';

    public function tone(): string
    {
        return match ($this) {
            self::Unchecked => 'neutral',
            self::Ok => 'success',
            self::Missing => 'warning',
            self::Error => 'danger',
        };
    }
}
