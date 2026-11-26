<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Vendor = auto-created for every store (TenantProvisioned); independent = signed up as
 * a partner.
 */
enum AffiliateType: string
{
    use HasEnumHelpers;

    case Vendor = 'vendor';
    case Independent = 'independent';

    public function tone(): string
    {
        return $this === self::Vendor ? 'brand' : 'info';
    }
}
