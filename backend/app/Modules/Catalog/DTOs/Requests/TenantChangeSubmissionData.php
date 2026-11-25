<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Modules\Catalog\Enums\TenantChangeType;
use App\Shared\DTOs\BaseDTO;

/**
 * Vendor submission of a store change request (full wanted id list).
 */
final class TenantChangeSubmissionData extends BaseDTO
{
    /**
     * @param  list<int>  $requestedIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly TenantChangeType $type,
        public readonly array $requestedIds,
        public readonly ?string $vendorNote = null,
        public readonly ?string $submittedBy = null,
    ) {}
}
