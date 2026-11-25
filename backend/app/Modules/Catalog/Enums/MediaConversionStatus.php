<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * State of the queued AVIF conversion of a gallery image.
 */
enum MediaConversionStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
