<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin "assign plan manually" (subscriptions page now, vendor profile later).
 */
final class AssignPlanData extends BaseDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $planId,
        public readonly BillingTerm $term,
        public readonly bool $markPaid = true,
        public readonly ?int $adminId = null,
    ) {}
}
