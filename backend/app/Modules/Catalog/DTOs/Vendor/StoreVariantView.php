<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantOverride;
use App\Shared\Money\Money;

/**
 * A central variant as the store sells it: central row ⊕ the store's variant override.
 */
final readonly class StoreVariantView
{
    public function __construct(
        public ProductVariant $central,
        public ?VariantOverride $override,
        public string $label,
        public int $stock,
        public int $weightGrams,
        public bool $weightOverridden,
        public ?string $imageUrl,
    ) {}

    public function id(): int
    {
        return $this->central->id;
    }

    public function costBasis(): Money
    {
        return $this->central->costBasis();
    }
}
