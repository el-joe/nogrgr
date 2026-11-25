<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Store KYC review: needs_review (submitted) → verified | needs_action (note to vendor) | rejected.
 * The vendor re-submits from needs_action / rejected, which puts it back to needs_review.
 */
enum ComplianceStatus: string
{
    use HasEnumHelpers;

    case NeedsReview = 'needs_review';
    case NeedsAction = 'needs_action';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this !== $next && match ($this) {
            self::NeedsReview => true,
            self::NeedsAction, self::Rejected => $next === self::NeedsReview || $next === self::Verified,
            self::Verified => $next === self::NeedsAction || $next === self::Rejected,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NeedsReview => 'warning',
            self::NeedsAction, self::Rejected => 'danger',
            self::Verified => 'success',
        };
    }
}
