<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\DTOs\Promotions\PromotionCoupon;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\FlashSaleStatus;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read side of the vendor Coupons / Flash sales pages (VEN-040 / 041, Figma 87:107268 /
 * 87:108154): the merged readers split into the "My …" (store) and "Nogrgr … (read-only)"
 * (central) tabs, the store's selling countries, and the typed product picker (imported
 * central products + own products → ProductRef keys "central:12" / "own:3").
 */
class VendorPromotionPageService
{
    public const TAB_OWN = 'own';

    public const TAB_CENTRAL = 'central';

    public const PER_PAGE = 20;

    public function __construct(
        private readonly CouponCatalog $coupons,
        private readonly FlashSaleCatalog $sales,
        private readonly StoreCouponRepositoryContract $storeCoupons,
        private readonly CouponRepositoryContract $centralCoupons,
        private readonly StoreFlashSaleRepositoryContract $storeSales,
        private readonly StoreProductRepositoryContract $products,
        private readonly CountryService $countries,
    ) {}

    public static function tab(mixed $value): string
    {
        return $value === self::TAB_CENTRAL ? self::TAB_CENTRAL : self::TAB_OWN;
    }

    /**
     * The store's selling countries (every country when it has none set).
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        $options = $this->countries->options();
        $selling = array_map('intval', StoreContext::tenant()->selling_country_ids ?? []);

        return $selling === [] ? $options : array_intersect_key($options, array_flip($selling));
    }

    /**
     * Country id => ISO code (the reach line of a row: "All products · SA, AE").
     *
     * @return array<int, string>
     */
    public function countryCodes(): array
    {
        return $this->countries->active()->mapWithKeys(static fn ($country): array => [(int) $country->id => (string) $country->iso2])->all();
    }

    /**
     * Coupons of a tab with their usage (store: this store; central: every store, the
     * limit is global). Central rows include the ones the store hid (tagged hidden).
     *
     * @return Collection<int, array{coupon: PromotionCoupon, used: int}>
     */
    public function coupons(string $tab, ?int $countryId = null): Collection
    {
        $source = $tab === self::TAB_CENTRAL ? DiscountSource::Central : DiscountSource::Vendor;
        $rows = $this->coupons->all($countryId, includeHidden: true)
            ->filter(static fn (PromotionCoupon $coupon): bool => $coupon->source === $source)
            ->values();
        $ids = $rows->map(static fn (PromotionCoupon $coupon): int => $coupon->id)->all();
        $usage = $source === DiscountSource::Central ? $this->centralCoupons->usageCounts($ids) : $this->storeCoupons->usageCounts($ids);

        return $rows->map(static fn (PromotionCoupon $coupon): array => ['coupon' => $coupon, 'used' => $usage[$coupon->id] ?? 0]);
    }

    /**
     * @return array{own: int, central: int}
     */
    public function couponCounts(): array
    {
        $all = $this->coupons->all(includeHidden: true);

        return [
            self::TAB_OWN => $all->where('source', DiscountSource::Vendor)->count(),
            self::TAB_CENTRAL => $all->where('source', DiscountSource::Central)->count(),
        ];
    }

    /**
     * A central coupon offered to this store (hidden or not), or null.
     */
    public function centralCoupon(int $id): ?PromotionCoupon
    {
        return $this->coupons->all(includeHidden: true)
            ->first(static fn (PromotionCoupon $coupon): bool => $coupon->source === DiscountSource::Central && $coupon->id === $id);
    }

    /**
     * @return Collection<int, PromotionFlashSale>
     */
    public function flashSales(string $tab, ?int $countryId = null): Collection
    {
        $source = $tab === self::TAB_CENTRAL ? DiscountSource::Central : DiscountSource::Vendor;

        return $this->sales->all($countryId, includeHidden: true)
            ->filter(static fn (PromotionFlashSale $sale): bool => $sale->source === $source)
            ->values();
    }

    /**
     * @return array{own: int, central: int}
     */
    public function flashSaleCounts(): array
    {
        $all = $this->sales->all(includeHidden: true);

        return [
            self::TAB_OWN => $all->where('source', DiscountSource::Vendor)->count(),
            self::TAB_CENTRAL => $all->where('source', DiscountSource::Central)->count(),
        ];
    }

    /**
     * Titles of the central sales live or upcoming in the store (the note under the list).
     *
     * @return list<string>
     */
    public function runningCentralSaleTitles(): array
    {
        return $this->sales->all()
            ->filter(static fn (PromotionFlashSale $sale): bool => $sale->source === DiscountSource::Central && in_array($sale->status(), [FlashSaleStatus::Live, FlashSaleStatus::Scheduled], true))
            ->map(static fn (PromotionFlashSale $sale): string => $sale->title)
            ->values()
            ->all();
    }

    public function centralFlashSale(int $id): ?PromotionFlashSale
    {
        return $this->sales->all(includeHidden: true)
            ->first(static fn (PromotionFlashSale $sale): bool => $sale->source === DiscountSource::Central && $sale->id === $id);
    }

    public function findCoupon(int $id): StoreCoupon
    {
        /** @var StoreCoupon $coupon */
        $coupon = $this->storeCoupons->findOrFail($id);

        return $coupon;
    }

    public function couponUsage(StoreCoupon $coupon): int
    {
        return $this->storeCoupons->usageCount($coupon->id);
    }

    public function findSale(int $id): StoreFlashSale
    {
        /** @var StoreFlashSale $sale */
        $sale = $this->storeSales->findOrFail($id);

        return $sale;
    }

    /**
     * The store's sales that have not ended (VEN-041b radio list).
     *
     * @return EloquentCollection<int, StoreFlashSale>
     */
    public function openSales(): EloquentCollection
    {
        return $this->storeSales->openSales();
    }

    /**
     * Select2 remote search over the store's products (imported central + own).
     *
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function searchProducts(?string $term, int $page = 1): array
    {
        $query = $this->products->query()->with('centralProduct')->orderBy('sort_order')->orderBy('id');

        if ($term !== null) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $centralIds = $this->products->centralIdsMatching($term);

            $query->where(static fn (Builder $any) => $any
                ->where(static fn (Builder $own) => $own->where('source', StoreProductSource::Own)->where(static fn (Builder $match) => $match->where('name', 'like', $like)->orWhere('sku', 'like', $like)))
                ->orWhere(static fn (Builder $central) => $central->where('source', StoreProductSource::Central)->whereIn('central_product_id', $centralIds)));
        }

        $results = $query->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'results' => collect($results->items())
                ->map(fn (StoreProduct $product): array => ['id' => $this->ref($product)->key(), 'text' => $this->label($product)])
                ->values()
                ->all(),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    /**
     * Labels of the given refs (key => label) for a saved sale; refs whose product left
     * the store keep a "#id" label so the vendor can still remove them.
     *
     * @param  list<ProductRef>  $refs
     * @return array<string, string>
     */
    public function productOptions(array $refs): array
    {
        $found = $this->findProducts($refs);
        $options = [];

        foreach ($refs as $ref) {
            $product = $found[$ref->key()] ?? null;
            $options[$ref->key()] = $product !== null ? $this->label($product) : '#'.$ref->id;
        }

        return $options;
    }

    /**
     * Typed refs for picker keys; null when one is malformed or not sold by the store.
     *
     * @param  list<string>  $keys
     * @return list<ProductRef>|null
     */
    public function resolveRefs(array $keys): ?array
    {
        $refs = [];

        foreach ($keys as $key) {
            if (preg_match('/^(central|own):([1-9]\d{0,18})$/', $key, $match) !== 1) {
                return null;
            }

            $refs[$key] = new ProductRef(StoreProductSource::from($match[1]), (int) $match[2]);
        }

        $refs = array_values($refs);

        return count($this->findProducts($refs)) === count($refs) ? $refs : null;
    }

    public function findProduct(int $storeProductId): StoreProduct
    {
        /** @var StoreProduct $product */
        $product = $this->products->query()->with('centralProduct')->findOrFail($storeProductId);

        return $product;
    }

    public function ref(StoreProduct $product): ProductRef
    {
        return $product->source === StoreProductSource::Central
            ? ProductRef::central((int) $product->central_product_id)
            : ProductRef::own($product->id);
    }

    public function label(StoreProduct $product): string
    {
        $name = $product->source === StoreProductSource::Central ? $product->centralProduct?->name : $product->name;
        $sku = $product->source === StoreProductSource::Central ? $product->centralProduct?->sku : $product->sku;

        return trim(implode(' · ', array_filter([trim((string) $name), (string) $sku], static fn (string $part): bool => $part !== ''))) ?: '#'.$product->id;
    }

    /**
     * @param  list<ProductRef>  $refs
     * @return array<string, StoreProduct> keyed by ProductRef::key()
     */
    private function findProducts(array $refs): array
    {
        if ($refs === []) {
            return [];
        }

        $own = array_map(static fn (ProductRef $ref): int => $ref->id, array_values(array_filter($refs, static fn (ProductRef $ref): bool => $ref->source === StoreProductSource::Own)));
        $central = array_map(static fn (ProductRef $ref): int => $ref->id, array_values(array_filter($refs, static fn (ProductRef $ref): bool => $ref->source === StoreProductSource::Central)));

        return $this->products->query()
            ->with('centralProduct')
            ->where(static fn (Builder $any) => $any
                ->where(static fn (Builder $query) => $query->where('source', StoreProductSource::Own)->whereIn('id', $own))
                ->orWhere(static fn (Builder $query) => $query->where('source', StoreProductSource::Central)->whereIn('central_product_id', $central)))
            ->get()
            ->mapWithKeys(fn (StoreProduct $product): array => [$this->ref($product)->key() => $product])
            ->all();
    }
}
