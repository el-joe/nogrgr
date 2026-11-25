<?php

namespace App\Modules\AfterSales\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One order line (and units) of a customer return request.
 */
final class ReturnLineData extends BaseDTO
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $quantity,
    ) {}
}
