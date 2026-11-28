<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Engagement\Services\FavoritesService;
use App\Modules\Identity\Models\Customer;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Support\Facades\Auth;

/**
 * Index entries → card view data (display-currency prices, product URLs, favourites).
 */
class ProductCards
{
    /**
     * @var array<int, true>|null
     */
    private ?array $favorites = null;

    public function __construct(private readonly StorefrontContext $context) {}

    /**
     * @param  iterable<IndexEntry>  $entries
     * @return list<ProductCardData>
     */
    public function make(iterable $entries): array
    {
        $cards = [];

        foreach ($entries as $entry) {
            $cards[] = $this->card($entry);
        }

        return $cards;
    }

    public function card(IndexEntry $entry): ProductCardData
    {
        $discount = $entry->discount();
        $record = $entry->badgeIds !== [] ? app(BadgeShelves::class)->forProduct($entry->badgeIds) : null;
        $badge = $record !== null ? (string) $record->name : null;
        [$ribbon, $ribbonKind] = match (true) {
            $discount !== null && $entry->flash !== null => [__st('catalog.card.ribbon_flash', ['percent' => $discount]), 'flash'],
            $discount !== null => [__st('catalog.card.ribbon_discount', ['percent' => $discount]), 'discount'],
            $badge !== null => [$badge, 'badge'],
            default => [null, null],
        };

        return new ProductCardData(
            id: $entry->id,
            name: $entry->name,
            url: self::url($entry->id, $entry->slug),
            image: $entry->image,
            image2: $entry->image2,
            price: $this->context->formatMoney($entry->price),
            compare: $entry->compare !== null && $entry->compare > $entry->price ? $this->context->formatMoney($entry->compare) : null,
            discount: $entry->discount(),
            flash: $entry->flash,
            rating: $entry->rating,
            reviewCount: $entry->reviewCount,
            starSeller: $entry->featured,
            inStock: $entry->inStock,
            hasVariants: $entry->hasVariants,
            favorited: isset($this->favoriteIds()[$entry->id]),
            soldCount: $entry->soldCount,
            priceMinor: $entry->price,
            stockLeft: $entry->stockLeft,
            weight: $entry->weightGrams !== null ? self::weight($entry->weightGrams) : null,
            badge: $badge,
            ribbon: $ribbon,
            ribbonKind: $ribbonKind,
            summary: $entry->summary,
            badgeType: $record?->type->value,
        );
    }

    /**
     * Shipping weight label of a card ("200g", "1.2kg").
     */
    public static function weight(int $grams): string
    {
        return $grams >= 1000 ? rtrim(rtrim(number_format($grams / 1000, 2, '.', ''), '0'), '.').'kg' : $grams.'g';
    }

    public static function url(int $id, string $slug): string
    {
        return route('storefront.product', ['product' => $id, 'slug' => $slug]);
    }

    /**
     * @return array<int, true>
     */
    private function favoriteIds(): array
    {
        if ($this->favorites !== null) {
            return $this->favorites;
        }

        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Customer || ! class_exists(FavoritesService::class)) {
            return $this->favorites = [];
        }

        return $this->favorites = array_fill_keys(app(FavoritesService::class)->idsFor($customer), true);
    }
}
