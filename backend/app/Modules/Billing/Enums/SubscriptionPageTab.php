<?php

namespace App\Modules\Billing\Enums;

/**
 * Tabs of the vendor Subscription & balance page (VEN-060 / 060c / 060d).
 */
enum SubscriptionPageTab: string
{
    case Overview = 'overview';
    case History = 'history';
    case Transactions = 'transactions';

    public function label(): string
    {
        return __('billing::vendor_subscription.tabs.'.$this->value);
    }

    public static function fromQuery(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Overview) : self::Overview;
    }
}
