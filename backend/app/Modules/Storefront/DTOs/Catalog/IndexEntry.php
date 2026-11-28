<?php

namespace App\Modules\Storefront\DTOs\Catalog;

/**
 * One sellable store product in the storefront index of a (store, country, locale):
 * everything the cards, listings, filters, search and sitemap need, already priced for
 * the country (USD minor units; display conversion happens at render time).
 *
 *   price    = cheapest variant after the live flash sale (what the shopper pays)
 *   regular  = the same before the flash sale
 *   compare  = struck-through price (regular when on flash, else the central list price)
 */
final readonly class IndexEntry
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $badgeIds
     * @param  array<int, list<string>>  $options  attribute id => option names (variation filters)
     * @param  array{percentage: float, starts_at: string, ends_at: string}|null  $flash
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $sku,
        public ?string $image,
        public ?string $image2,
        public int $price,
        public int $regular,
        public ?int $compare,
        public ?array $flash,
        public bool $inStock,
        public bool $hasVariants,
        public array $categoryIds,
        public array $badgeIds,
        public array $options,
        public bool $featured,
        public int $soldCount,
        public float $rating,
        public int $reviewCount,
        public int $createdAt,
        public int $updatedAt,
        public int $sortOrder,
        public ?int $stockLeft = null,
        public ?int $weightGrams = null,
        public ?string $summary = null,
    ) {}

    /**
     * Discount of the price over the compare price, in whole percent (null without one).
     */
    public function discount(): ?int
    {
        if ($this->compare === null || $this->compare <= $this->price || $this->compare === 0) {
            return null;
        }

        $percent = (int) round(($this->compare - $this->price) / $this->compare * 100);

        return $percent > 0 ? $percent : null;
    }

    /**
     * Plain array for the cache (cache stores only unserialize plain data).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    public function searchText(): string
    {
        return mb_strtolower($this->name.' '.($this->sku ?? ''));
    }
}
