<?php

namespace App\Modules\Affiliates\DTOs;

use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Shared\DTOs\BaseDTO;

/**
 * AFF-013a payout details: PayPal e-mail or bank account (IBAN stored encrypted).
 */
final class PayoutDetailsData extends BaseDTO
{
    public function __construct(
        public readonly PayoutMethod $method,
        public readonly ?string $paypalEmail = null,
        public readonly ?string $bankName = null,
        public readonly ?string $bankAccountName = null,
        public readonly ?string $bankIban = null,
    ) {}
}
