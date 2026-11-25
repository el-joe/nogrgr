<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * One purchasable combination of an external product. Money in USD minor units;
 * `priceMinor` null = the product price.
 */
final class ExternalVariantData extends BaseDTO
{
    /**
     * @param  list<ExternalOptionData>  $options
     */
    public function __construct(
        public readonly int $externalId,
        public readonly ?string $sku,
        public readonly ?int $priceMinor,
        public readonly int $stock,
        public readonly ?int $weightGrams,
        public readonly bool $isActive,
        public readonly array $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'options' => array_map(static fn (ExternalOptionData $option): array => $option->toArray(), $this->options)];
    }
}
