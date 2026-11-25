<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Shared\DTOs\BaseDTO;

/**
 * Everything TenantProvisioningService needs to create a store (admin "Create vendor"
 * or a completed central-website registration). The owner password is passed hashed
 * (registration) or left null (admin create: the owner signs in with the password the
 * admin set, given as ownerPassword and hashed by the service).
 */
final class ProvisionTenantData extends BaseDTO
{
    /**
     * @param  list<int>  $sellingCountryIds
     * @param  list<int>  $sellingCategoryIds  top-level catalog categories the store sells
     */
    public function __construct(
        public readonly string $storeName,
        public readonly string $slug,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly ?string $ownerPhone = null,
        public readonly ?string $ownerPassword = null,
        public readonly ?string $ownerPasswordHash = null,
        public readonly ?string $customDomain = null,
        public readonly ?int $defaultCountryId = null,
        public readonly array $sellingCountryIds = [],
        public readonly int $profitPercentBps = 0,
        public readonly ?int $planId = null,
        public readonly ?BillingTerm $term = null,
        public readonly PlanAssignment $planAssignment = PlanAssignment::Trial,
        public readonly ?string $locale = null,
        public readonly ?int $registrationId = null,
        public readonly ?int $adminId = null,
        public readonly ?int $ownerId = null,
        public readonly ?int $paymentLogId = null,
        public readonly array $sellingCategoryIds = [],
    ) {}
}
