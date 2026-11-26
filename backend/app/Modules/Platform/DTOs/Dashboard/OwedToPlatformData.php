<?php

namespace App\Modules\Platform\DTOs\Dashboard;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * Vendor dashboard "pay the platform" alert (VEN-009a): the ledger receivable of the
 * store (USD) and the orders still waiting for a settlement.
 */
final class OwedToPlatformData extends BaseDTO
{
    public function __construct(
        public readonly Money $amount,
        public readonly int $orders,
        public readonly string $payUrl,
    ) {}

    public function isSettled(): bool
    {
        return ! $this->amount->isPositive();
    }
}
