<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Store KYC review: draft (vendor still filling the compliance center, never in the admin
 * queue) → needs_review (submitted) → verified | needs_action (note to vendor) | rejected.
 * The vendor re-submits from needs_action / rejected, which puts it back to needs_review.
 */
enum ComplianceStatus: string
{
    use HasEnumHelpers;

    case Draft = 'draft';
    case NeedsReview = 'needs_review';
    case NeedsAction = 'needs_action';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return $this !== $next && match ($this) {
            self::Draft => $next === self::NeedsReview,
            self::NeedsReview => $next !== self::Draft,
            self::NeedsAction, self::Rejected => $next === self::NeedsReview || $next === self::Verified,
            self::Verified => $next === self::NeedsAction || $next === self::Rejected,
        };
    }

    /**
     * Statuses of the admin review queue (a draft was never submitted).
     *
     * @return list<self>
     */
    public static function reviewable(): array
    {
        return [self::NeedsReview, self::NeedsAction, self::Verified, self::Rejected];
    }

    /**
     * The vendor may still edit the compliance center (not waiting for review, not verified).
     */
    public function isEditableByVendor(): bool
    {
        return in_array($this, [self::Draft, self::NeedsAction, self::Rejected], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::NeedsReview => 'warning',
            self::NeedsAction, self::Rejected => 'danger',
            self::Verified => 'success',
        };
    }
}
