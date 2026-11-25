<?php

namespace App\Modules\Billing\DTOs\Finance;

use App\Modules\Billing\Enums\PayoutMethod;
use App\Shared\DTOs\BaseDTO;
use Carbon\CarbonImmutable;

/**
 * Create payout for a store (ADM-052a). Amount in USD minor units.
 */
final class PayoutData extends BaseDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $amount,
        public readonly PayoutMethod $method,
        public readonly CarbonImmutable $scheduledFor,
        public readonly ?string $transactionReference = null,
        public readonly ?string $note = null,
        public readonly bool $notifyVendor = false,
    ) {}
}
