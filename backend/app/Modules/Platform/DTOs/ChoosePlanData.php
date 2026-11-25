<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\PlanChoice;
use App\Shared\DTOs\BaseDTO;

/**
 * Plan step (WEB-016b): plan, billing term and trial / pay now.
 */
final class ChoosePlanData extends BaseDTO
{
    public function __construct(
        public readonly int $planId,
        public readonly BillingTerm $term,
        public readonly PlanChoice $choice,
    ) {}
}
