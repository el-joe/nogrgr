<?php

namespace App\Modules\Storefront\DTOs\Catalog;

/**
 * Everything a product card renders (Ecommet cards 1:1067 deal / 1:1089 grid), already
 * formatted in the display currency.
 *
 * `badge` = the product's own badge (assigned / auto-filled central badge); `ribbon` = the ONE corner label
 * (discount / flash first, then the badge; `ribbonKind` discount|flash|badge), decided per product.
 *
 * @property-read array{percentage: float, starts_at: string, ends_at: string}|null $flash
 */
final readonly class ProductCardData
{
    /**
     * @param  array{percentage: float, starts_at: string, ends_at: string}|null  $flash
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public ?string $image,
        public ?string $image2,
        public string $price,
        public ?string $compare,
        public ?int $discount,
        public ?array $flash,
        public float $rating,
        public int $reviewCount,
        public bool $starSeller,
        public bool $inStock,
        public bool $hasVariants,
        public bool $favorited,
        public int $soldCount,
        public int $priceMinor,
        public ?int $stockLeft = null,
        public ?string $weight = null,
        public ?string $badge = null,
        public ?string $ribbon = null,
        public ?string $ribbonKind = null,
    ) {}

    /**
     * "Only N left" hint for low stock (Elora cards); null when stock is unlimited or plenty.
     */
    public function lowStock(int $threshold = 10): ?int
    {
        return $this->inStock && $this->stockLeft !== null && $this->stockLeft > 0 && $this->stockLeft <= $threshold ? $this->stockLeft : null;
    }

    /**
     * Elapsed share of the flash sale window (0–100) for the deal card progress bar.
     */
    public function flashProgress(): int
    {
        if ($this->flash === null) {
            return 0;
        }

        $start = strtotime($this->flash['starts_at']) ?: time();
        $end = strtotime($this->flash['ends_at']) ?: time();

        return $end <= $start ? 100 : (int) max(0, min(100, round((time() - $start) / ($end - $start) * 100)));
    }
}
