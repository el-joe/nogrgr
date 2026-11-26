<?php

namespace App\Modules\Affiliates\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * ADM-060c: referral cookie days, default commission (basis points) and minimum
 * payout (USD cents).
 */
final class ProgramSettingsData extends BaseDTO
{
    public function __construct(
        public readonly int $cookieDays,
        public readonly int $defaultCommissionBps,
        public readonly int $minPayoutCents,
    ) {}
}
