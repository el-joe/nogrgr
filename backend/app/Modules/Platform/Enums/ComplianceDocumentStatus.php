<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ComplianceDocumentStatus: string
{
    use HasEnumHelpers;

    case Review = 'review';
    case Verified = 'verified';
    case NeedsAction = 'needs_action';

    public function tone(): string
    {
        return match ($this) {
            self::Review => 'warning',
            self::Verified => 'success',
            self::NeedsAction => 'danger',
        };
    }
}
