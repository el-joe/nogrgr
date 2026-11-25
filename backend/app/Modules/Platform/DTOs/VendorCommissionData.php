<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor profit % and affiliate commission, in basis points (100 bps = 1 %).
 */
final class VendorCommissionData extends BaseDTO
{
    public function __construct(
        public readonly int $profitPercentBps,
        public readonly int $affiliateCommissionBps,
    ) {}
}
