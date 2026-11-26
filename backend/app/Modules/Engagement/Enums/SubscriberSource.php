<?php

namespace App\Modules\Engagement\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a newsletter subscription came from (VEN-026 "Source"): linked to a customer
 * account (registration, checkout, the vendor's customer form) or the store's public
 * signup form (footer / popup, no account).
 */
enum SubscriberSource: string
{
    use HasEnumHelpers;

    case Account = 'account';
    case SignupForm = 'signup_form';

    public static function forCustomerId(?int $customerId): self
    {
        return $customerId !== null ? self::Account : self::SignupForm;
    }

    public function label(): string
    {
        return __('engagement::subscribers.sources.'.$this->value);
    }
}
