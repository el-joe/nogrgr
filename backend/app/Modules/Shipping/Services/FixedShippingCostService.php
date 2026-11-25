<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Pricing\FixedShipping;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Modules\Commerce\Pricing\UnitPricing;
use App\Modules\Shipping\DTOs\FixedShippingCostData;
use App\Modules\Shipping\Jobs\RecomputeFixedShippingCosts;
use App\Modules\Shipping\Models\FixedShippingCost;
use App\Modules\Shipping\Repositories\Contracts\FixedShippingCostRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Fixed shipping costs (ADM-031): one USD-per-gram rate per country. Every change
 * re-derives the precomputed fixed_shipping_costs map of the whole catalog in a queued
 * job; the money maths stays in Commerce\Pricing (FixedShipping / UnitPricing).
 */
class FixedShippingCostService extends BaseService
{
    public const EXAMPLE_WEIGHT_GRAMS = 500;

    public function __construct(private readonly FixedShippingCostRepositoryContract $costs) {}

    /**
     * @return array<int, string> country_id => price_per_gram
     */
    public function ratesByCountry(): array
    {
        return $this->costs->ratesByCountry();
    }

    public function create(FixedShippingCostData $data): FixedShippingCost
    {
        $cost = $this->transaction(fn (): FixedShippingCost => $this->costs->create(['country_id' => $data->countryId, 'price_per_gram' => $data->pricePerGram]), $this->connection());
        $this->recomputeCatalog();

        return $cost;
    }

    public function update(FixedShippingCost $cost, FixedShippingCostData $data): FixedShippingCost
    {
        $this->transaction(fn () => $this->costs->update($cost, ['country_id' => $data->countryId, 'price_per_gram' => $data->pricePerGram]), $this->connection());
        $this->recomputeCatalog();

        return $cost;
    }

    public function delete(FixedShippingCost $cost): void
    {
        $this->transaction(fn () => $this->costs->delete($cost), $this->connection());
        $this->recomputeCatalog();
    }

    /**
     * Fixed shipping of one line of $weightGrams (once per line, not × qty).
     */
    public function shippingFor(int $weightGrams, string $pricePerGram): Money
    {
        return FixedShipping::forWeight($weightGrams, $pricePerGram);
    }

    /**
     * Calculator (Figma 71:42662): display price = sale price + weight × rate, through UnitPricing.
     *
     * @return array{shipping: Money, display: Money, rate: string|null}
     */
    public function calculate(int $countryId, int $weightGrams, Money $salePrice): array
    {
        $rate = $this->costs->forCountry($countryId)?->price_per_gram;
        $shipping = $rate === null ? Money::zero() : FixedShipping::forWeight($weightGrams, $rate);
        $unit = UnitPricing::unitPrice($salePrice, ProfitRule::percentage(0.0), $shipping);

        return ['shipping' => $shipping, 'display' => $unit->displayPrice(), 'rate' => $rate];
    }

    /**
     * Queues the catalog recompute once the surrounding transaction (if any) commits.
     *
     * @param  list<int>|null  $productIds  null = whole catalog
     */
    public function recomputeCatalog(?array $productIds = null): void
    {
        DB::afterCommit(static fn () => RecomputeFixedShippingCosts::dispatch($productIds));
    }

    private function connection(): ?string
    {
        return (new FixedShippingCost)->getConnectionName();
    }
}
