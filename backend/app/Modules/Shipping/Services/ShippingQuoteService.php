<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\QuoteLine;
use App\Modules\Shipping\DTOs\ShippingQuote;
use App\Modules\Shipping\DTOs\ShippingQuoteRequest;
use App\Modules\Shipping\Enums\ShippingUnavailableReason;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Modules\Shipping\Support\BandDefinition;
use App\Modules\Shipping\Support\RuleDefinition;
use App\Shared\Money\Money;

/**
 * Order shipping charge from the weight / zone rules (old PlacesOrders::resolveShippingCalculation,
 * ORDER_FORMULAS.md §1 "shipping_charge … computed from catalog lines only"):
 *
 *   line_weight  = unit weight_g × qty            (own products: 0, not shippable)
 *   total_weight = Σ line_weight of shippable lines
 *   no shippable line              → available, 0
 *   no country                     → unavailable (country_required)
 *   no active rule for the country → unavailable (country_not_served)
 *   no zone for the destination    → unavailable (no_zone)
 *   first band (min ≤ w ≤ max, by min then max) → its price; no band → free
 *   line fees: amount split by line weight (by qty when all weights are 0), each share
 *              rounded to cents, the last line takes the remainder (Σ fees = amount).
 *
 * calculate() is pure; quote() only loads the country's active rule first.
 */
class ShippingQuoteService
{
    public function __construct(private readonly ShippingRuleRepositoryContract $rules) {}

    public function quote(ShippingQuoteRequest $request): ShippingQuote
    {
        $rule = $request->countryId !== null && $this->hasShippableLine($request->lines)
            ? $this->rules->definitionForCountry($request->countryId)
            : null;

        return self::calculate($rule, $request);
    }

    /**
     * Free-shipping threshold of a destination in grams (cart progress bar, old
     * CalculatesFreeShipping): the lowest min weight of a band priced 0 in the resolved
     * zone. Null = the destination has no free band.
     */
    public function freeShippingThreshold(?int $countryId, ?int $cityId = null): ?int
    {
        $zone = $countryId !== null ? $this->rules->definitionForCountry($countryId)?->zoneFor($cityId) : null;
        $free = array_filter($zone->bands ?? [], static fn (BandDefinition $band): bool => $band->price->isZero());

        return $free === [] ? null : min(array_map(static fn (BandDefinition $band): int => $band->minGrams, $free));
    }

    public static function calculate(?RuleDefinition $rule, ShippingQuoteRequest $request): ShippingQuote
    {
        $zero = Money::zero();
        $lineWeights = [];
        $lineFees = [];

        foreach ($request->lines as $line) {
            $lineWeights[$line->key] = $line->lineWeightGrams();
            $lineFees[$line->key] = $zero;
        }

        $shippable = array_values(array_filter($request->lines, static fn (QuoteLine $line): bool => $line->shippable));
        $totalWeight = array_sum(array_map(static fn (QuoteLine $line): int => $line->lineWeightGrams(), $shippable));

        if ($shippable === []) {
            return new ShippingQuote(true, $zero, 0, $lineFees, $lineWeights);
        }

        $unavailable = static fn (ShippingUnavailableReason $reason, ?int $ruleId = null): ShippingQuote => new ShippingQuote(false, $zero, $totalWeight, $lineFees, $lineWeights, $reason, $ruleId);

        if ($request->countryId === null) {
            return $unavailable(ShippingUnavailableReason::CountryRequired);
        }

        if ($rule === null) {
            return $unavailable(ShippingUnavailableReason::CountryNotServed);
        }

        $zone = $rule->zoneFor($request->cityId, $request->zoneId);

        if ($zone === null) {
            return $unavailable(ShippingUnavailableReason::NoZone, $rule->id);
        }

        $band = $zone->bandFor($totalWeight);
        $amount = $band->price ?? $zero;

        return new ShippingQuote(
            available: true,
            amount: $amount,
            totalWeightGrams: $totalWeight,
            lineFees: self::allocate($amount, $shippable, $lineFees),
            lineWeights: $lineWeights,
            ruleId: $rule->id,
            zoneId: $zone->id,
            zoneName: $zone->name,
            bandId: $band?->id,
            bandName: $band?->name,
        );
    }

    /**
     * Old PlacesOrders::allocateShippingFees with Money (cents rounding like OrderPricing::money()).
     *
     * @param  list<QuoteLine>  $shippable
     * @param  array<string, Money>  $lineFees
     * @return array<string, Money>
     */
    public static function allocate(Money $amount, array $shippable, array $lineFees): array
    {
        if (! $amount->isPositive() || $shippable === []) {
            return $lineFees;
        }

        $metrics = [];

        foreach ($shippable as $line) {
            $metrics[$line->key] = $line->lineWeightGrams();
        }

        $total = array_sum($metrics);

        if ($total <= 0) {
            foreach ($shippable as $line) {
                $metrics[$line->key] = max(1, $line->quantity);
            }

            $total = array_sum($metrics);
        }

        $remaining = $amount;
        $lastKey = array_key_last($metrics);

        foreach ($metrics as $key => $metric) {
            if ($key === $lastKey) {
                $lineFees[$key] = $remaining;

                continue;
            }

            $allocated = Money::of($amount->toFloat() * ($metric / $total), $amount->currency);
            $lineFees[$key] = $allocated;
            $remaining = $remaining->subtract($allocated);
        }

        return $lineFees;
    }

    /**
     * @param  list<QuoteLine>  $lines
     */
    private function hasShippableLine(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($line->shippable) {
                return true;
            }
        }

        return false;
    }
}
