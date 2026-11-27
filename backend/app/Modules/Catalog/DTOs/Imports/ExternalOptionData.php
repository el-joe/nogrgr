<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * One attribute value of an external variant (e.g. Color = Black), with the external
 * ids of both the attribute and the option and its colour swatch (#hex) when known.
 */
final class ExternalOptionData extends BaseDTO
{
    /**
     * @param  array<string, string>  $attributeName  locale => name
     * @param  array<string, string>  $name  locale => option name
     */
    public function __construct(
        public readonly int $attributeExternalId,
        public readonly array $attributeName,
        public readonly int $externalId,
        public readonly array $name,
        public readonly ?string $swatch = null,
    ) {}
}
