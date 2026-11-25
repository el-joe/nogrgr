<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a customer cannot open a return on an order (Store API options / create).
 */
enum ReturnBlock: string
{
    use HasEnumHelpers;

    case OrderNotReturnable = 'order_not_returnable';
    case WindowExpired = 'return_window_expired';
    case NothingReturnable = 'nothing_returnable';

    public function message(): string
    {
        return (string) __('after-sales::messages.errors.'.$this->value);
    }
}
