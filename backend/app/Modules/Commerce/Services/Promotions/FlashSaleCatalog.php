<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Commerce\DTOs\Promotions\FlashDiscount;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Models\StoreFlashSaleProduct;
use App\Modules\Commerce\Repositories\Contracts\FlashSaleRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreFlashSaleRepositoryContract;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The ONE flash-sale reader of a store (decision #65 b): store sales (tenant DB) merged
 * with the central sales that target the store (central DB), tagged with `source`.
 * Central sales cover central products only; store sales cover central and own products.
 *
 * Discount of a product = the highest % among the live sales covering it in the country
 * (old FlashSaleCatalog::discountExpression = GREATEST(central, own)); on a tie the
 * central sale wins, so the platform absorbs it (ORDER_FORMULAS.md §2 discount_source).
 */
class FlashSaleCatalog
{
    public function __construct(
        private readonly FlashSaleRepositoryContract $central,
        private readonly StoreFlashSaleRepositoryContract $store,
        private readonly HiddenPromotionRepositoryContract $hidden,
    ) {}

    /**
     * @return Collection<int, PromotionFlashSale> store sales first, then central
     */
    public function all(?int $countryId = null, bool $liveOnly = false, bool $includeHidden = false): Collection
    {
        return $this->merged(null, $liveOnly, $includeHidden)
            ->filter(fn (PromotionFlashSale $sale): bool => $countryId === null || $sale->appliesToCountry($countryId))
            ->values();
    }

    /**
     * Best live discount per product (keyed by ProductRef::key()); products without a
     * live sale are absent.
     *
     * @param  list<ProductRef>  $products
     * @return array<string, FlashDiscount>
     */
    public function discountsFor(array $products, ?int $countryId, ?CarbonInterface $at = null): array
    {
        if ($products === []) {
            return [];
        }

        $at ??= CarbonImmutable::now();
        $sales = $this->merged($products, false, false)
            ->filter(fn (PromotionFlashSale $sale): bool => $sale->isLive($at) && $sale->appliesToCountry($countryId))
            ->sortBy(fn (PromotionFlashSale $sale): int => $sale->source === DiscountSource::Central ? 0 : 1);

        $best = [];

        foreach ($products as $product) {
            foreach ($sales as $sale) {
                $current = $best[$product->key()] ?? null;

                if ($sale->covers($product) && ($current === null || $sale->percentage > $current->percentage)) {
                    $best[$product->key()] = new FlashDiscount($sale->percentage, $sale->source, $sale->id);
                }
            }
        }

        return $best;
    }

    /**
     * @param  list<ProductRef>|null  $products
     * @return Collection<int, PromotionFlashSale>
     */
    private function merged(?array $products, bool $liveOnly, bool $includeHidden): Collection
    {
        $tenant = StoreContext::tenant();
        $own = $this->store->listing($products, $liveOnly)->map(fn (StoreFlashSale $sale): PromotionFlashSale => $this->fromStore($sale));

        $centralIds = $products === null ? null : array_values(array_map(
            static fn (ProductRef $ref): int => $ref->id,
            array_filter($products, static fn (ProductRef $ref): bool => $ref->source === StoreProductSource::Central),
        ));

        if ($centralIds === []) {
            return $own->values();
        }

        $hidden = array_flip($this->hidden->hiddenIds(PromotionKind::FlashSale));

        $central = $this->central->applicableToTenant((int) $tenant->getTenantKey(), $centralIds, $liveOnly)
            ->map(function (FlashSale $sale) use ($tenant, $hidden, $includeHidden): ?PromotionFlashSale {
                $isHidden = isset($hidden[$sale->id]);
                $countries = StoreContext::trimCountries($tenant, $sale->countries->pluck('id')->map(static fn ($id): int => (int) $id)->all());

                return $countries === null || ($isHidden && ! $includeHidden) ? null : $this->fromCentral($sale, $countries, $isHidden);
            })
            ->filter();

        return $own->concat($central)->values();
    }

    /**
     * @param  list<int>  $countryIds
     */
    private function fromCentral(FlashSale $sale, array $countryIds, bool $hidden): PromotionFlashSale
    {
        return new PromotionFlashSale(
            source: DiscountSource::Central,
            id: $sale->id,
            title: (string) $sale->title,
            percentage: (float) $sale->discount_percentage,
            startsAt: CarbonImmutable::instance($sale->starts_at),
            endsAt: CarbonImmutable::instance($sale->ends_at),
            active: $sale->is_active,
            bannerUrl: $sale->bannerUrl(),
            countryIds: $countryIds,
            products: $sale->products->map(static fn ($product): ProductRef => ProductRef::central((int) $product->id))->values()->all(),
            hidden: $hidden,
        );
    }

    private function fromStore(StoreFlashSale $sale): PromotionFlashSale
    {
        return new PromotionFlashSale(
            source: DiscountSource::Vendor,
            id: $sale->id,
            title: (string) $sale->title,
            percentage: (float) $sale->discount_percentage,
            startsAt: CarbonImmutable::instance($sale->starts_at),
            endsAt: CarbonImmutable::instance($sale->ends_at),
            active: $sale->is_active,
            bannerUrl: $sale->bannerUrl(),
            countryIds: $sale->countryRows->pluck('country_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all(),
            products: $sale->productRows->map(static fn (StoreFlashSaleProduct $row): ProductRef => new ProductRef($row->product_source, $row->product_id))->values()->all(),
        );
    }
}
