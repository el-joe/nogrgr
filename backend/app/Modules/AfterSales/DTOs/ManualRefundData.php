<?php

namespace App\Modules\AfterSales\DTOs;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * Staff refund of an amount ≤ refundable (ADM-021b / VEN-021b).
 */
final class ManualRefundData extends BaseDTO
{
    public function __construct(
        public readonly Money $amount,
        public readonly string $reason,
        public readonly bool $restock = false,
    ) {}
}
