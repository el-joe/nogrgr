<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * Save of the category mapping screen: source mapping id => central category id (null = unmapped).
 */
final class CategoryMappingData extends BaseDTO
{
    /**
     * @param  array<int, int|null>  $mappings
     */
    public function __construct(public readonly array $mappings) {}
}
