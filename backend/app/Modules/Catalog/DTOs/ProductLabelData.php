<?php

namespace App\Modules\Catalog\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a product label. `countryIds` null = every country.
 */
final class ProductLabelData extends BaseDTO
{
    /**
     * @param  array<string, string>  $text
     * @param  list<int>|null  $countryIds
     */
    public function __construct(
        public readonly array $text,
        public readonly ?string $icon,
        public readonly bool $isActive,
        public readonly ?int $sortOrder = null,
        public readonly ?array $countryIds = null,
    ) {}
}
