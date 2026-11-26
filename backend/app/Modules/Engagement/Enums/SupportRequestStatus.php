<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Storefront support request (SF-ECO-035) handling state.
 */
enum SupportRequestStatus: string
{
    use HasEnumHelpers;

    case New = 'new';
    case InProgress = 'in_progress';
    case Closed = 'closed';
}
