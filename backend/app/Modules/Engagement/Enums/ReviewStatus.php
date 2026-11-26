<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Product review moderation state: only approved reviews are public and counted in the
 * summary. Pending = waiting for the store (when approval is required); hidden = taken
 * down after publishing.
 */
enum ReviewStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
}
