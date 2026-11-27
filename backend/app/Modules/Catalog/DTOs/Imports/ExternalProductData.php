<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Shared\DTOs\BaseDTO;

/**
 * A product read from an external catalog, mapped by its driver to this neutral shape.
 * Translatable fields are locale => text; money in USD minor units; `stock` null =
 * the sum of the variants.
 */
final class ExternalProductData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $summary
     * @param  array<string, string>  $description
     * @param  array<string, string>  $metaDescription
     * @param  array<string, string>  $metaKeywords
     * @param  list<ExternalImageData>  $images
     * @param  list<ExternalImageData>  $videos  gallery videos (#114)
     * @param  list<ExternalVariantData>  $variants
     * @param  list<ExternalCategoryData>  $categories
     */
    public function __construct(
        public readonly int $externalId,
        public readonly string $sku,
        public readonly array $name,
        public readonly int $priceMinor,
        public readonly bool $isActive,
        public readonly array $summary = [],
        public readonly array $description = [],
        public readonly array $metaDescription = [],
        public readonly array $metaKeywords = [],
        public readonly ?int $weightGrams = null,
        public readonly bool $isTaxable = false,
        public readonly bool $requiresShipping = true,
        public readonly bool $unlimitedStock = false,
        public readonly ?int $stock = null,
        public readonly array $images = [],
        public readonly array $variants = [],
        public readonly array $categories = [],
        public readonly array $videos = [],
    ) {}

    public function displayName(): string
    {
        return $this->name['en'] ?? $this->name['ar'] ?? (array_values($this->name)[0] ?? $this->sku);
    }

    public function totalStock(): int
    {
        if ($this->stock !== null) {
            return max(0, $this->stock);
        }

        return array_sum(array_map(static fn (ExternalVariantData $variant): int => max(0, $variant->stock), $this->variants));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            ...parent::toArray(),
            'images' => array_map(static fn (ExternalImageData $image): array => $image->toArray(), $this->images),
            'variants' => array_map(static fn (ExternalVariantData $variant): array => $variant->toArray(), $this->variants),
            'categories' => array_map(static fn (ExternalCategoryData $category): array => $category->toArray(), $this->categories),
            'videos' => array_map(static fn (ExternalImageData $video): array => $video->toArray(), $this->videos),
        ];

        // Products without videos keep the checksum they had before videos were imported
        // (#114), so only products that have videos are re-imported on the next run.
        if ($this->videos === []) {
            unset($data['videos']);
        }

        return $data;
    }

    /**
     * Fingerprint of the payload: an unchanged item is skipped on re-runs.
     */
    public function checksum(): string
    {
        return hash('sha256', (string) json_encode($this->toArray()));
    }
}
