<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PayoutRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Rejected => 'danger',
        };
    }
}
