<?php

namespace App\Modules\Billing\DTOs\Finance;

use App\Shared\DTOs\BaseDTO;

/**
 * The admin who records a payout / settlement.
 */
final class FinanceActor extends BaseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $name,
    ) {}
}
