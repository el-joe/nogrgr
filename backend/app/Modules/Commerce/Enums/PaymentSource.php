<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Whose gateway account takes a storefront payment: the store's own keys (tenant) or the
 * platform's central credentials (spec 08 §1 `source`).
 */
enum PaymentSource: string
{
    use HasEnumHelpers;

    case Tenant = 'tenant';
    case Central = 'central';
}
