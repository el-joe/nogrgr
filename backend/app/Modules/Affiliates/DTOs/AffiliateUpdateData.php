<?php

namespace App\Modules\Affiliates\DTOs;

use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Identity\Enums\AccountStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * ADM-060a: own commission rate (null type = program default; value in bps or cents)
 * and account status.
 */
final class AffiliateUpdateData extends BaseDTO
{
    public function __construct(
        public readonly ?RateType $commissionType,
        public readonly ?int $commissionValue,
        public readonly AccountStatus $status,
    ) {}
}
