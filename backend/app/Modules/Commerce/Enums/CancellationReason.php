<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Reason codes of an order cancellation (orders.cancel_reason_code). Customers pick from
 * the customer list, store staff and admins from the staff list; "other" needs a note.
 */
enum CancellationReason: string
{
    use HasEnumHelpers;

    case ChangedMind = 'changed_mind';
    case OrderedByMistake = 'ordered_by_mistake';
    case FoundBetterPrice = 'found_better_price';
    case DeliveryTooLong = 'delivery_too_long';
    case WrongAddressOrDetails = 'wrong_address_or_details';
    case PaymentIssue = 'payment_issue';
    case DuplicateOrder = 'duplicate_order';
    case OutOfStock = 'out_of_stock';
    case UnableToFulfil = 'unable_to_fulfil';
    case SuspectedFraud = 'suspected_fraud';
    case CustomerRequest = 'customer_request';
    case PricingError = 'pricing_error';
    case Other = 'other';

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    public function forStaff(): bool
    {
        return in_array($this, [self::OutOfStock, self::UnableToFulfil, self::SuspectedFraud, self::CustomerRequest, self::PricingError, self::Other], true);
    }

    /**
     * @return array<string, string>
     */
    public static function staffOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($case->forStaff()) {
                $options[$case->value] = $case->label();
            }
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_keys(self::staffOptions());
    }
}
