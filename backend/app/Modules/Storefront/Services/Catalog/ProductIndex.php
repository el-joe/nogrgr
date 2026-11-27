<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Modules\Commerce\DTOs\Promotions\FlashDiscount;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Pricing\OrderPricing;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPrice;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Commerce\Services\Promotions\FlashSaleCatalog;
use App\Modules\Commerce\Services\Stock\StockReservationService;
use App\Modules\Engagement\Services\Reviews\ReviewSummaryReader;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StoreOwnCategory;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The storefront product index of one (store, shopping country, locale): every product the
 * store sells in that country, priced exactly like the cart (CartLineResolver rules:
 * active for the store, available centrally, sold in the country, StoreProductPricingService
 * unit prices, FlashSaleCatalog discounts, OrderPricing line maths). Listings, filters,
 * search, home rails and the sitemap all read this one list, so a card never shows a
 * product the cart would refuse. Cached for a few minutes (and never past the next flash
 * sale start / end); product pages re-price live through ProductPageService.
 */
class ProductIndex
{
    public const TTL = 300;

    private const CHUNK = 100;

    /**
     * @var array<string, array{entries: array<int, IndexEntry>, attributes: array<int, string>}>
     */
    private array $memo = [];

    public function __construct(
        private readonly StorefrontCatalogRepositoryContract $products,
        private readonly StoreCatalogReader $reader,
        private readonly StoreProductPricingService $pricing,
        private readonly FlashSaleCatalog $flashSales,
        private readonly StockReservationService $stock,
        private readonly StoreCountries $countries,
        private readonly StorefrontContext $context,
    ) {}

    /**
     * @return array<int, IndexEntry> keyed by store product id, in store order
     */
    public function entries(): array
    {
        return $this->load()['entries'];
    }

    public function entry(int $id): ?IndexEntry
    {
        return $this->entries()[$id] ?? null;
    }

    /**
     * Attribute id => name of every variation used by the indexed products.
     *
     * @return array<int, string>
     */
    public function attributes(): array
    {
        return $this->load()['attributes'];
    }

    public function forget(): void
    {
        app('cache')->store()->forget($this->key());
        $this->memo = [];
    }

    /**
     * Prices one loaded product for the country (product page, live).
     *
     * @return array{price: int, regular: int, compare: int|null, flash: FlashDiscount|null, variants: array<int, array{price: int, regular: int, compare: int|null, stock: int|null}>, in_stock: bool, stock_left: int|null}|null
     */
    public function price(StoreProductView $view, int $countryId): ?array
    {
        $ref = $this->ref($view);
        $flash = $this->flashSales->discountsFor([$ref], $countryId)[$ref->key()] ?? null;
        $sellable = $this->stock->sellable($ref->source, [$ref->id]);

        return $this->priceView($view, $ref, $countryId, $flash, $sellable);
    }

    /**
     * @return array{entries: array<int, IndexEntry>, attributes: array<int, string>}
     */
    private function load(): array
    {
        $key = $this->key();

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $countryId = $this->context->countryId();

        if ($countryId === null || ! $this->countries->isAvailable($countryId)) {
            return $this->memo[$key] = ['entries' => [], 'attributes' => []];
        }

        $cached = app('cache')->store()->get($key);

        if (is_array($cached) && isset($cached['entries'], $cached['attributes']) && array_filter($cached['entries'], 'is_array') === $cached['entries']) {
            return $this->memo[$key] = [
                'entries' => array_map(static fn (array $entry): IndexEntry => IndexEntry::fromArray($entry), $cached['entries']),
                'attributes' => $cached['attributes'],
            ];
        }

        $sales = $this->flashSales->all($countryId);
        $built = $this->build($countryId, $sales->filter(fn (PromotionFlashSale $sale): bool => $sale->isLive())->keyBy(fn (PromotionFlashSale $sale): string => $sale->key())->all());
        app('cache')->store()->put($key, [
            'entries' => array_map(static fn (IndexEntry $entry): array => $entry->toArray(), $built['entries']),
            'attributes' => $built['attributes'],
        ], $this->ttl($sales->all()));

        return $this->memo[$key] = $built;
    }

    /**
     * @param  array<string, PromotionFlashSale>  $liveSales
     * @return array{entries: array<int, IndexEntry>, attributes: array<int, string>}
     */
    private function build(int $countryId, array $liveSales): array
    {
        $entries = [];
        $attributeIds = [];

        $this->products->eachActiveChunk(self::CHUNK, function (Collection $products) use ($countryId, $liveSales, &$entries, &$attributeIds): void {
            /** @var Collection<int, StoreProduct> $products */
            $views = $this->reader->views($products);
            $refs = [];
            $ids = [StoreProductSource::Central->value => [], StoreProductSource::Own->value => []];

            foreach ($views as $view) {
                $refs[$view->id()] = $ref = $this->ref($view);
                $ids[$ref->source->value][] = $ref->id;
            }

            $sellable = [
                StoreProductSource::Central->value => $this->stock->sellable(StoreProductSource::Central, $ids[StoreProductSource::Central->value]),
                StoreProductSource::Own->value => $this->stock->sellable(StoreProductSource::Own, $ids[StoreProductSource::Own->value]),
            ];
            $discounts = $this->flashSales->discountsFor(array_values($refs), $countryId);
            $ratings = $this->ratings(array_map(static fn (StoreProductView $view): int => $view->id(), $views));

            foreach ($views as $view) {
                $ref = $refs[$view->id()];
                $flash = $discounts[$ref->key()] ?? null;
                $priced = $view->product->is_active && $view->available
                    ? $this->priceView($view, $ref, $countryId, $flash, $sellable[$ref->source->value])
                    : null;

                if ($priced === null) {
                    continue;
                }

                $options = $this->options($view);

                foreach (array_keys($options) as $attributeId) {
                    $attributeIds[$attributeId] = true;
                }

                $sale = $flash !== null ? ($liveSales[$flash->source->value.':'.$flash->flashSaleId] ?? null) : null;
                $entries[$view->id()] = new IndexEntry(
                    id: $view->id(),
                    name: $view->name,
                    slug: $this->slug($view),
                    sku: $view->sku,
                    image: $view->imageUrls[0] ?? null,
                    image2: $view->imageUrls[1] ?? null,
                    price: $priced['price'],
                    regular: $priced['regular'],
                    compare: $priced['compare'],
                    flash: $flash !== null && $sale !== null ? ['percentage' => $flash->percentage, 'starts_at' => $sale->startsAt->toIso8601String(), 'ends_at' => $sale->endsAt->toIso8601String()] : null,
                    inStock: $priced['in_stock'],
                    hasVariants: count($priced['variants']) > 0,
                    categoryIds: [...$view->categoryIds, ...array_map(static fn (mixed $id): int => StoreOwnCategory::idFor((int) $id), $view->product->store_category_ids ?? [])],
                    badgeIds: $view->product->badge_ids !== null ? array_map('intval', $view->product->badge_ids) : $view->badgeIds,
                    options: $options,
                    featured: $view->product->is_featured,
                    soldCount: max((int) $view->product->sold_count, (int) ($view->central->sold_count ?? 0)),
                    rating: $ratings[$view->id()]['average'] ?? 0.0,
                    reviewCount: $ratings[$view->id()]['count'] ?? 0,
                    createdAt: (int) $view->product->created_at?->getTimestamp(),
                    updatedAt: (int) max($view->product->updated_at?->getTimestamp() ?? 0, $view->central?->updated_at?->getTimestamp() ?? 0),
                    sortOrder: $view->product->sort_order,
                    stockLeft: $priced['stock_left'],
                    weightGrams: $view->weightGrams > 0 ? $view->weightGrams : null,
                );
            }
        });

        $attributes = $attributeIds === [] ? [] : Attribute::query()->whereIn('id', array_keys($attributeIds))->get()
            ->mapWithKeys(static fn (Attribute $attribute): array => [$attribute->id => (string) $attribute->name])
            ->all();

        return ['entries' => $entries, 'attributes' => $attributes];
    }

    /**
     * Same sellability rules as CartLineResolver::line(); null = not sold in the country.
     *
     * @param  array<string, int|null>  $sellable
     * @return array{price: int, regular: int, compare: int|null, flash: FlashDiscount|null, variants: array<int, array{price: int, regular: int, compare: int|null, stock: int|null}>, in_stock: bool, stock_left: int|null}|null
     */
    private function priceView(StoreProductView $view, ProductRef $ref, int $countryId, ?FlashDiscount $flash, array $sellable): ?array
    {
        if (! $view->product->is_active || ! $view->available || ! $this->countries->isAvailable($countryId)) {
            return null;
        }

        $pct = $flash->percentage ?? 0.0;
        $rows = [];

        if ($view->isOwn()) {
            $allowed = $view->product->country_ids;

            if ($allowed !== null && $allowed !== [] && ! in_array($countryId, array_map('intval', $allowed), true)) {
                return null;
            }

            $variants = $view->product->variants->where('is_active', true);

            if ($variants->isEmpty()) {
                $rows[0] = $this->ownRow($view->product->ownSellPrice(), $view->product->price, $pct, $flash) + ['stock' => $sellable[$ref->id.':0'] ?? null];
            }

            foreach ($variants as $variant) {
                /** @var StoreProductVariant $variant */
                $list = $variant->sale_price !== null && $variant->sale_price->isPositive() ? $variant->price : ($variant->price === null ? $view->product->price : null);
                $rows[$variant->id] = $this->ownRow($variant->ownSellPrice() ?? $view->product->ownSellPrice(), $list, $pct, $flash) + ['stock' => $sellable[$ref->id.':'.$variant->id] ?? null];
            }
        } else {
            $targets = $view->variants === [] ? [null] : $view->variants;

            foreach ($targets as $variant) {
                $unit = $this->pricing->unitFor($view->product, $variant?->id(), $countryId);

                if ($unit === null) {
                    continue;
                }

                $list = $variant === null || $variant->central->price === null ? $view->central?->compareAtPrice() : null;
                $weight = $variant !== null ? $variant->weightGrams : $view->weightGrams;
                $rows[$variant?->id() ?? 0] = $this->catalogRow($unit, $list, $view->costBasis(), $weight, $pct, $flash)
                    + ['stock' => $sellable[$ref->id.':'.($variant?->id() ?? 0)] ?? null];
            }
        }

        if ($rows === []) {
            return null;
        }

        $cheapest = collect($rows)->sortBy('price')->first();
        $inStock = collect($rows)->contains(static fn (array $row): bool => $row['stock'] === null || $row['stock'] > 0);
        $stockLeft = collect($rows)->contains(static fn (array $row): bool => $row['stock'] === null) ? null : (int) collect($rows)->sum('stock');

        return [
            'price' => $cheapest['price'],
            'regular' => $cheapest['regular'],
            'compare' => $cheapest['compare'],
            'flash' => $flash,
            'variants' => array_key_exists(0, $rows) && count($rows) === 1 ? [] : $rows,
            'in_stock' => $inStock,
            'stock_left' => $stockLeft,
        ];
    }

    /**
     * @return array{price: int, regular: int, compare: int|null}
     */
    private function catalogRow(UnitPrice $unit, ?Money $listPrice, ?Money $costBasis, int $weight, float $pct, ?FlashDiscount $flash): array
    {
        $line = OrderPricing::item(OrderLineInput::catalog('card', $unit, 1, $weight, $pct, $flash?->source));
        $regular = $line->displaySubtotal()->minor;
        $compare = null;

        if ($pct > 0) {
            $compare = $regular;
        } elseif ($listPrice !== null && $costBasis !== null && $listPrice->greaterThan($costBasis)) {
            // Same profit and fixed shipping on top of the central list price.
            $compare = UnitPricing::unitPrice($listPrice, ProfitRule::fixed($unit->profitAmount->toFloat()), $unit->fixedShipping)->displayPrice()->minor;
        }

        return ['price' => $line->grandTotal->minor, 'regular' => $regular, 'compare' => $compare];
    }

    /**
     * @return array{price: int, regular: int, compare: int|null}
     */
    private function ownRow(Money $sell, ?Money $listPrice, float $pct, ?FlashDiscount $flash): array
    {
        $line = OrderPricing::item(OrderLineInput::own('card', $sell, 1, $pct, $flash?->source));
        $regular = $line->displaySubtotal()->minor;
        $compare = $pct > 0 ? $regular : ($listPrice !== null && $listPrice->greaterThan($sell) ? UnitPricing::ownProduct($listPrice)->displayPrice()->minor : null);

        return ['price' => $line->grandTotal->minor, 'regular' => $regular, 'compare' => $compare];
    }

    /**
     * @return array<int, list<string>>
     */
    private function options(StoreProductView $view): array
    {
        $options = [];

        foreach ($view->variants as $variant) {
            foreach ($variant->central->options as $option) {
                /** @var AttributeOption $option */
                $options[$option->attribute_id][] = (string) $option->name;
            }
        }

        return array_map(static fn (array $names): array => array_values(array_unique($names)), $options);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{average: float, count: int}>
     */
    private function ratings(array $ids): array
    {
        if ($ids === [] || ! class_exists(ReviewSummaryReader::class)) {
            return [];
        }

        return app(ReviewSummaryReader::class)->summaries($ids);
    }

    private function slug(StoreProductView $view): string
    {
        $slug = $view->product->slug ?: ($view->central->slug ?? '');

        return $slug !== '' ? $slug : (Str::slug($view->name) ?: 'p');
    }

    private function ref(StoreProductView $view): ProductRef
    {
        return $view->isOwn() ? ProductRef::own($view->id()) : ProductRef::central((int) $view->product->central_product_id);
    }

    /**
     * Cache lifetime: never past the next flash sale start / end in the country.
     *
     * @param  list<PromotionFlashSale>  $sales
     */
    private function ttl(array $sales): int
    {
        $now = CarbonImmutable::now();
        $ttl = self::TTL;

        foreach ($sales as $sale) {
            foreach ([$sale->startsAt, $sale->endsAt] as $boundary) {
                if ($boundary->greaterThan($now)) {
                    $ttl = min($ttl, max(1, (int) $now->diffInSeconds($boundary)));
                }
            }
        }

        return $ttl;
    }

    /**
     * Bumps the cache version of one store (or of every store without an id), so the next read
     * rebuilds the index: a created / edited flash sale shows at once instead of after the TTL.
     */
    public static function bump(?int $tenantId = null): void
    {
        $key = 'sf:index:ver:'.($tenantId ?? 'all');
        $cache = app('cache')->store();

        $cache->put($key, ((int) $cache->get($key, 0)) + 1, 86400 * 30);
    }

    private static function version(string $tenant): string
    {
        $cache = app('cache')->store();

        return ((int) $cache->get('sf:index:ver:all', 0)).'.'.((int) $cache->get('sf:index:ver:'.$tenant, 0));
    }

    private function key(): string
    {
        $tenant = tenancy()->initialized ? (string) tenant()?->getTenantKey() : 'central';

        return 'sf:index:v2:'.self::version($tenant).':'.$tenant.':'.($this->context->countryId() ?? 0).':'.app()->getLocale();
    }
}
