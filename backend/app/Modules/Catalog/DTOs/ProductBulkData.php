<?php

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\ProductBulkAction;
use App\Shared\DTOs\BaseDTO;

/**
 * Products list bulk action on the selected rows.
 */
final class ProductBulkData extends BaseDTO
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public readonly ProductBulkAction $action,
        public readonly array $productIds,
    ) {}
}
