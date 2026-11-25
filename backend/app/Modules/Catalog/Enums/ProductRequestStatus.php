<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Product request (vendor asks the platform to source a new product).
 * pending → in_review ⇄ waiting_vendor → added_to_catalog | rejected.
 */
enum ProductRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case InReview = 'in_review';
    case WaitingVendor = 'waiting_vendor';
    case AddedToCatalog = 'added_to_catalog';
    case Rejected = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::InReview => 'info',
            self::WaitingVendor => 'warning',
            self::AddedToCatalog => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InReview, self::WaitingVendor], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::InReview->value, self::WaitingVendor->value];
    }
}
