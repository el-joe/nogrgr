<?php

namespace App\Modules\Billing\DTOs;

use App\Shared\DTOs\BaseDTO;

final class InvoiceLineData extends BaseDTO
{
    public function __construct(
        public readonly string $description,
        public readonly int $unitAmountMinor,
        public readonly int $quantity = 1,
    ) {}
}
