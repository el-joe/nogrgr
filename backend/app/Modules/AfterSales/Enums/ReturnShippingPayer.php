<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who pays the return shipping under the platform policy.
 */
enum ReturnShippingPayer: string
{
    use HasEnumHelpers;

    case CustomerUnlessFault = 'customer_unless_fault';
    case Customer = 'customer';
    case Store = 'store';
}
