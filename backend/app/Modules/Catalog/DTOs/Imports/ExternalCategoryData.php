<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * A category reference of an external product (driver-neutral).
 */
final class ExternalCategoryData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => name
     */
    public function __construct(
        public readonly int $externalId,
        public readonly array $name,
        public readonly ?int $parentExternalId = null,
    ) {}

    public function displayName(): string
    {
        return $this->name['en'] ?? $this->name['ar'] ?? (array_values($this->name)[0] ?? '#'.$this->externalId);
    }
}
