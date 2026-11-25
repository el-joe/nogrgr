<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * One page of an external catalog: the mapped products plus the raw items the driver
 * could not map (logged as errors without stopping the page).
 */
final class ExternalCatalogPage extends BaseDTO
{
    /**
     * @param  list<ExternalProductData>  $items
     * @param  list<array{external_id: int|null, sku: string|null, message: string}>  $invalid
     */
    public function __construct(
        public readonly int $page,
        public readonly int $lastPage,
        public readonly int $total,
        public readonly array $items,
        public readonly array $invalid = [],
    ) {}

    public function count(): int
    {
        return count($this->items) + count($this->invalid);
    }
}
