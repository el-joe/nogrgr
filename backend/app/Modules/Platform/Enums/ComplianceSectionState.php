<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * State of one compliance center step, shown in the stepper and the Status card.
 */
enum ComplianceSectionState: string
{
    use HasEnumHelpers;

    case Incomplete = 'incomplete';
    case Complete = 'complete';
    case InReview = 'in_review';
    case NeedsAction = 'needs_action';
    case Verified = 'verified';
    case Accepted = 'accepted';

    public function tone(): string
    {
        return match ($this) {
            self::Incomplete => 'neutral',
            self::Complete => 'info',
            self::InReview => 'warning',
            self::NeedsAction => 'warning',
            self::Verified, self::Accepted => 'success',
        };
    }

    /**
     * The vendor has to do something in this step.
     */
    public function needsAttention(): bool
    {
        return $this === self::Incomplete || $this === self::NeedsAction;
    }

    /**
     * The stepper shows the step as done (green check).
     */
    public function isDone(): bool
    {
        return ! $this->needsAttention();
    }
}
