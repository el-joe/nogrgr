<?php

namespace App\Modules\Billing\DTOs\Finance;

use App\Modules\Billing\Enums\PayoutStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Edit payout (ADM-052b): status and transaction reference.
 */
final class PayoutUpdateData extends BaseDTO
{
    public function __construct(
        public readonly PayoutStatus $status,
        public readonly ?string $transactionReference = null,
    ) {}
}
