<?php

namespace App\Modules\Catalog\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Products to put on a badge list: picked ones and / or every product of a category
 * (and its sub-categories).
 */
final class BadgeAssignmentData extends BaseDTO
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public readonly array $productIds = [],
        public readonly ?int $categoryId = null,
    ) {}
}
