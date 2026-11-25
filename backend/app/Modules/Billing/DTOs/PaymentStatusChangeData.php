<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Shared\DTOs\BaseDTO;

final class PaymentStatusChangeData extends BaseDTO
{
    public function __construct(
        public readonly PaymentLogStatus $status,
        public readonly ?string $note = null,
    ) {}
}
