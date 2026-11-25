<?php

namespace App\Modules\Catalog\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * One generated variant row. `key` = VariantMatrix::key(optionIds) and links the row to
 * its stock cells; `priceMinor` null = the product price; `id` null = new variant.
 */
final class ProductVariantData extends BaseDTO
{
    /**
     * @param  list<int>  $optionIds
     */
    public function __construct(
        public readonly string $key,
        public readonly ?int $id,
        public readonly array $optionIds,
        public readonly ?string $sku,
        public readonly ?int $priceMinor,
        public readonly ?int $weightGrams,
        public readonly bool $isActive = true,
        public readonly ?UploadedFile $image = null,
        public readonly bool $removeImage = false,
    ) {}
}
