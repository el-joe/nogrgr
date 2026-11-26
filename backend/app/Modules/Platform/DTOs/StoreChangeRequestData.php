<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Catalog\Enums\TenantChangeType;
use App\Shared\DTOs\BaseDTO;

/**
 * Vendor › Settings › General change request modal (VEN-071a countries / 071b
 * categories): ids to add, ids to remove and the vendor's reason.
 */
final class StoreChangeRequestData extends BaseDTO
{
    /**
     * @param  list<int>  $add
     * @param  list<int>  $remove
     */
    public function __construct(
        public readonly TenantChangeType $type,
        public readonly array $add,
        public readonly array $remove,
        public readonly ?string $reason,
        public readonly ?string $submittedBy,
    ) {}
}
