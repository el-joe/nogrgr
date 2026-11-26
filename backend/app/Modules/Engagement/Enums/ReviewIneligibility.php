<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a visitor cannot write a review of a product: not signed in, no delivered order
 * line for it, or every delivered line already reviewed.
 */
enum ReviewIneligibility: string
{
    use HasEnumHelpers;

    case Guest = 'guest';
    case NotPurchased = 'not_purchased';
    case AlreadyReviewed = 'already_reviewed';

    /**
     * Static-text key of the customer-facing message.
     */
    public function textKey(): string
    {
        return 'reviews.errors.'.$this->value;
    }
}
