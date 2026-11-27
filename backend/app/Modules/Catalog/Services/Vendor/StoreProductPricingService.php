<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\MarkupData;
use App\Modules\Catalog\DTOs\Vendor\StorePriceRow;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\DTOs\Vendor\StoreVariantView;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Commerce\Pricing\FixedShipping;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPrice;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Shipping\Services\FixedShippingCostService;
use App\Shared\Money\Money;
use Illuminate\Support\Collection;

/**
 * Store prices of imported central products (VEN-010a / 012a). All maths goes through
 * Commerce\Pricing (CountryPriceTable / UnitPricing / FixedShipping); this class only
 * resolves which profit row applies and which fixed shipping a country gets:
 *
 *   rule(variant, country) = variant_markup[variant][country] → markup[country]
 *                          → variant_markup[variant][default] → markup[default]
 *                          → tenant default % (tenants.profit_percent_bps / 100)
 *   price = cost basis (variant price, else sale price > 0, else base) + profit_amount
 *           + fixed shipping of that country (central precomputed map; recomputed with
 *             FixedShipping when an approved edit request overrides the weight).
 *
 * Markups are store business data written directly by the vendor (store_products).
 */
class StoreProductPricingService
{
    /**
     * @var array<int, string>|null
     */
    private ?array $rates = null;

    public function __construct(
        private readonly CountryService $countries,
        private readonly StoreCatalogReader $reader,
        private readonly FixedShippingCostService $fixedShipping,
    ) {}

    public function defaultPercentage(): float
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant->profit_percent_bps / 100 : 0.0;
    }

    /**
     * Countries the store sells a central product in = the store's selling countries
     * (every central product sells in every country, #115).
     *
     * @return Collection<int, Country>
     */
    public function countriesFor(?Product $central): Collection
    {
        $tenant = tenant();
        $selling = $tenant instanceof Tenant ? array_map('intval', $tenant->selling_country_ids ?? []) : [];

        if ($selling === []) {
            return new Collection;
        }

        return $this->countries->active($selling)->values();
    }

    /**
     * Full price table (variant × country) of an imported product, optionally with a markup
     * preview applied to the matching rows.
     *
     * @return list<StorePriceRow>
     */
    public function table(StoreProduct $product, ?MarkupData $preview = null): array
    {
        $view = $this->reader->view($product);
        $central = $view->central;

        if ($central === null || $view->isOwn()) {
            return [];
        }

        $countries = $this->countriesFor($central);

        if ($countries->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($view->variants === [] ? [null] : $view->variants as $variant) {
            /** @var StoreVariantView|null $variant */
            $profit = $this->profitMap($product, $variant?->id(), $countries, $preview);
            $base = $variant?->costBasis() ?? $central->costBasis();
            $shipping = $this->shippingMap($view, $variant, $countries);
            $result = CountryPriceTable::forCatalog($base, $shipping, $profit, $this->defaultPercentage(), $countries->pluck('id')->all());

            foreach ($countries as $country) {
                $key = (string) $country->id;
                $rows[] = new StorePriceRow(
                    variantId: $variant?->id(),
                    variantLabel: $variant?->label,
                    countryId: $country->id,
                    countryName: (string) $country->name,
                    iso2: $country->iso2,
                    cost: $base,
                    shipping: $shipping[$key],
                    rule: ProfitRule::fromArray($result['profit'][$key]),
                    profit: Money::of($result['profit'][$key]['total_profit']),
                    price: $result['prices'][$key],
                );
            }
        }

        return $rows;
    }

    /**
     * Unit split of one imported product (or one of its central variants) for one
     * destination country (cart / checkout lines, P5): cost basis, profit_amount by the
     * markup precedence and the country's fixed shipping. Null when the store does not
     * sell the product in that country.
     */
    public function unitFor(StoreProduct $product, ?int $variantId, int $countryId): ?UnitPrice
    {
        $view = $this->reader->view($product);
        $central = $view->central;

        if ($central === null || $view->isOwn()) {
            return null;
        }

        $countries = $this->countriesFor($central)->filter(static fn (Country $country): bool => $country->id === $countryId)->values();

        if ($countries->isEmpty()) {
            return null;
        }

        $variant = $variantId !== null ? $view->variant($variantId) : null;

        if ($variantId !== null && $variant === null) {
            return null;
        }

        $key = (string) $countryId;
        $rule = $this->profitMap($product, $variant?->id(), $countries, null)[$key] ?? ProfitRule::percentage($this->defaultPercentage());
        $shipping = $this->shippingMap($view, $variant, $countries)[$key];

        return UnitPricing::unitPrice($variant?->costBasis() ?? $central->costBasis(), $rule, $shipping);
    }

    /**
     * Unit price of a not-yet-added central product with the store's default markup
     * (new product preview; fixed shipping depends on the destination and is not included).
     */
    public function defaultUnit(Product $central): UnitPrice
    {
        return UnitPricing::unitPrice($central->costBasis(), ProfitRule::percentage($this->defaultPercentage()));
    }

    /**
     * Shopper price of one own product unit (cost 0, profit = sell price, no fixed shipping).
     */
    public function ownPrice(StoreProduct $product, ?StoreProductVariant $variant = null): Money
    {
        $sell = $variant?->ownSellPrice() ?? $product->ownSellPrice();

        return UnitPricing::ownProduct($sell)->displayPrice();
    }

    /**
     * Lowest shopper price (cards "cost to your customer's door").
     */
    public function fromPrice(StoreProduct $product): ?Money
    {
        if ($product->isOwn()) {
            return $this->ownPrice($product);
        }

        $rows = $this->table($product);

        if ($rows === []) {
            return null;
        }

        return array_reduce($rows, static fn (?Money $carry, StorePriceRow $row): Money => $carry === null || $row->price->lessThan($carry) ? $row->price : $carry);
    }

    /**
     * Lowest-price row (cards: price + profit of the cheapest destination).
     */
    public function cheapestRow(StoreProduct $product): ?StorePriceRow
    {
        $rows = $this->table($product);

        return $rows === [] ? null : array_reduce($rows, static fn (?StorePriceRow $carry, StorePriceRow $row): StorePriceRow => $carry === null || $row->price->lessThan($carry->price) ? $row : $carry);
    }

    /**
     * Writes a markup into the store's profit rows for the given scope.
     *
     *  - no country, no variant: every product key (default + each store country) is set
     *    and all variant rows are cleared ("apply markup to all")
     *  - country only: that key of the product; the same key is cleared on every variant
     *  - variant (± country): that central variant's key ("default" when no country)
     */
    public function applyMarkup(StoreProduct $product, MarkupData $markup): void
    {
        $rule = $markup->rule()->jsonSerialize();
        $variantMarkup = $product->variant_markup ?? [];

        if ($markup->variantId !== null) {
            $rows = $variantMarkup[(string) $markup->variantId] ?? [];
            $rows[$markup->countryId !== null ? (string) $markup->countryId : CountryPriceTable::DEFAULT_KEY] = $rule;
            $variantMarkup[(string) $markup->variantId] = $rows;
            $product->forceFill(['variant_markup' => $variantMarkup])->save();

            return;
        }

        $rows = $product->markup ?? [];

        if ($markup->countryId !== null) {
            $rows[(string) $markup->countryId] = $rule;

            foreach ($variantMarkup as $variantId => $variantRows) {
                unset($variantRows[(string) $markup->countryId]);
                $variantMarkup[$variantId] = $variantRows;
            }

            $variantMarkup = array_filter($variantMarkup, static fn (array $variantRows): bool => $variantRows !== []);
        } else {
            $this->reader->load($product);
            $keys = [CountryPriceTable::DEFAULT_KEY, ...$this->countriesFor($product->centralProduct)->pluck('id')->map(static fn (int $id): string => (string) $id)->all()];

            foreach ($keys as $key) {
                $rows[$key] = $rule;
            }

            $variantMarkup = [];
        }

        $product->forceFill(['markup' => $rows, 'variant_markup' => $variantMarkup === [] ? null : $variantMarkup])->save();
    }

    /**
     * Saves per-row markups of the product edit pricing tab (variantKey => country => rule;
     * variantKey = "product" or a central variant id).
     *
     * @param  array<string, array<string, array{profit_type: string, profit_value: float|int|string}>>  $pricing
     */
    public function saveRows(StoreProduct $product, array $pricing): void
    {
        $product->forceFill($this->mergeRows($product, $pricing))->save();
    }

    /**
     * Price table with unsaved pricing-tab rows applied (live preview, nothing is written).
     *
     * @param  array<string, array<string, array{profit_type: string, profit_value: float|int|string}>>  $pricing
     * @return list<StorePriceRow>
     */
    public function previewRows(StoreProduct $product, array $pricing): array
    {
        $this->reader->load($product);
        $draft = clone $product;
        $draft->forceFill($this->mergeRows($product, $pricing));

        return $this->table($draft);
    }

    /**
     * Summary card of the pricing tab: central price, average store price, platform share
     * (100 % − the store's profit %), average profit per unit.
     *
     * @param  list<StorePriceRow>  $rows
     * @return array{central: string|null, average: string|null, platform_share: float, profit: string|null}
     */
    public function summary(StoreProductView $view, array $rows): array
    {
        $tenant = tenant();
        $share = $tenant instanceof Tenant ? (10000 - $tenant->profit_percent_bps) / 100 : 0.0;
        $count = count($rows);
        $average = static fn (callable $pick): ?string => $count > 0
            ? Money::ofMinor((int) round(array_sum(array_map($pick, $rows)) / $count))->format()
            : null;

        return [
            'central' => $view->costBasis()?->format(),
            'average' => $average(static fn (StorePriceRow $row): int => $row->price->minor),
            'platform_share' => max(0.0, $share),
            'profit' => $average(static fn (StorePriceRow $row): int => $row->profit->minor),
        ];
    }

    /**
     * @param  array<string, array<string, array{profit_type: string, profit_value: float|int|string}>>  $pricing
     * @return array{markup: array<string, mixed>|null, variant_markup: array<string, mixed>|null}
     */
    private function mergeRows(StoreProduct $product, array $pricing): array
    {
        $markup = $product->markup ?? [];
        $variantMarkup = $product->variant_markup ?? [];

        foreach ($pricing as $variantKey => $countries) {
            $rows = [];

            foreach ($countries as $countryId => $row) {
                $rows[(string) $countryId] = ProfitRule::fromArray($row)->jsonSerialize();
            }

            if ((string) $variantKey === 'product') {
                $markup = array_replace($markup, $rows);
            } else {
                $variantMarkup[(string) $variantKey] = array_replace($variantMarkup[(string) $variantKey] ?? [], $rows);
            }
        }

        return ['markup' => $markup === [] ? null : $markup, 'variant_markup' => $variantMarkup === [] ? null : $variantMarkup];
    }

    /**
     * Fixed shipping per country: the central precomputed map, or FixedShipping on the
     * overridden weight with the current rates.
     *
     * @param  Collection<int, Country>  $countries
     * @return array<int|string, Money>
     */
    private function shippingMap(StoreProductView $view, ?StoreVariantView $variant, Collection $countries): array
    {
        $overridden = $variant !== null ? $variant->weightOverridden : $view->weightOverridden;
        $map = [];

        if ($overridden) {
            $this->rates ??= $this->fixedShipping->ratesByCountry();
            $costs = FixedShipping::costMap($variant->weightGrams ?? $view->weightGrams, $this->rates) ?? [];

            foreach ($countries as $country) {
                $map[(string) $country->id] = Money::ofMinor($costs[(string) $country->id] ?? 0);
            }

            return $map;
        }

        foreach ($countries as $country) {
            $fixed = $variant !== null ? $variant->central->fixedShippingFor($country->id) : $view->central?->fixedShippingFor($country->id);
            $map[(string) $country->id] = $fixed ?? Money::zero();
        }

        return $map;
    }

    /**
     * Complete profit map {default, country…} for one variant, resolved by precedence,
     * with an optional preview markup applied to the matching scope.
     *
     * @param  Collection<int, Country>  $countries
     * @return array<string, ProfitRule>
     */
    private function profitMap(StoreProduct $product, ?int $variantId, Collection $countries, ?MarkupData $preview): array
    {
        $productRows = $product->markup ?? [];
        $variantRows = $variantId !== null ? ($product->variant_markup[(string) $variantId] ?? []) : [];
        $fallback = ProfitRule::percentage($this->defaultPercentage());
        $resolve = static function (string $key) use ($productRows, $variantRows, $fallback): ProfitRule {
            $row = $variantRows[$key] ?? $productRows[$key] ?? $variantRows[CountryPriceTable::DEFAULT_KEY] ?? $productRows[CountryPriceTable::DEFAULT_KEY] ?? null;

            return is_array($row) ? ProfitRule::fromArray($row) : $fallback;
        };

        $map = [CountryPriceTable::DEFAULT_KEY => $resolve(CountryPriceTable::DEFAULT_KEY)];

        foreach ($countries as $country) {
            $map[(string) $country->id] = $resolve((string) $country->id);
        }

        if ($preview !== null && ($preview->variantId === null || $preview->variantId === $variantId)) {
            foreach (array_keys($map) as $key) {
                if ($preview->countryId === null || (string) $preview->countryId === (string) $key) {
                    $map[$key] = $preview->rule();
                }
            }
        }

        return $map;
    }
}
