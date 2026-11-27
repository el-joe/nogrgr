<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * A category of an external catalog (driver-neutral): the reference on a product, or the
 * full record (slug, description, image) used to create a missing central category.
 */
final class ExternalCategoryData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name  locale => name
     * @param  array<string, string>  $description  locale => text
     */
    public function __construct(
        public readonly int $externalId,
        public readonly array $name,
        public readonly ?int $parentExternalId = null,
        public readonly ?string $slug = null,
        public readonly array $description = [],
        public readonly ?string $imageUrl = null,
    ) {}

    public function displayName(): string
    {
        return $this->name['en'] ?? $this->name['ar'] ?? (array_values($this->name)[0] ?? '#'.$this->externalId);
    }
}
