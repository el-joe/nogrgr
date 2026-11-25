<?php

use App\Modules\Shipping\DTOs\QuoteLine;
use App\Modules\Shipping\DTOs\ShippingQuoteRequest;
use App\Modules\Shipping\Enums\ShippingUnavailableReason;
use App\Modules\Shipping\Services\ShippingQuoteService;
use App\Modules\Shipping\Support\BandDefinition;
use App\Modules\Shipping\Support\RuleDefinition;
use App\Modules\Shipping\Support\ZoneDefinition;
use App\Shared\Money\Money;

/*
| ShippingQuoteService::calculate() — the old PlacesOrders weight / zone logic
| (ORDER_FORMULAS.md §1: shipping_charge from catalog lines only, own products free).
*/

function ksaRule(): RuleDefinition
{
    $bands = [
        new BandDefinition('Light', 0, 1000, Money::of(5), 11),
        new BandDefinition('Medium', 1001, 5000, Money::of(7.5), 12),
        new BandDefinition('Heavy', 5001, 30000, Money::of(15), 13),
    ];

    return new RuleDefinition('KSA standard', [
        new ZoneDefinition('Riyadh & Central', [101, 102], $bands, 1),
        new ZoneDefinition('Eastern', [201], [new BandDefinition('Light', 0, 1000, Money::of(4.5), 21)], 2),
        new ZoneDefinition('Rest of KSA', [], [new BandDefinition('Any', 0, null, Money::of(9), 31)], 3),
    ], 7);
}

function quote(?RuleDefinition $rule, array $lines, ?int $country = 1, ?int $city = null, ?int $zone = null)
{
    return ShippingQuoteService::calculate($rule, new ShippingQuoteRequest($country, $lines, $city, $zone));
}

it('golden: reproduces the UK worked example shipping (by weight) = 1.20', function (): void {
    // ORDER_FORMULAS.md §1 worked example: one catalog line, order shipping 1.2.
    $rule = new RuleDefinition('UK', [new ZoneDefinition('UK', [], [new BandDefinition('Up to 2 kg', 0, 2000, Money::of(1.2))])]);
    $result = quote($rule, [new QuoteLine('a', 1000, 1)]);

    expect($result->available)->toBeTrue()
        ->and($result->amount->toDecimal())->toBe('1.20')
        ->and($result->lineFees['a']->toDecimal())->toBe('1.20');

    // order_grand_total = items 181.935 (stored per line) + 1.2 - 0 coupon = 183.135
    expect(round(181.935 + $result->amount->toFloat(), 3))->toBe(183.135);
});

it('picks the first matching band of the city zone from the total line weight (weight × qty)', function (): void {
    $result = quote(ksaRule(), [new QuoteLine('a', 400, 2), new QuoteLine('b', 300, 1)], city: 101);

    expect($result->totalWeightGrams)->toBe(1100)
        ->and($result->lineWeights)->toBe(['a' => 800, 'b' => 300])
        ->and($result->zoneId)->toBe(1)
        ->and($result->bandName)->toBe('Medium')
        ->and($result->amount->toDecimal())->toBe('7.50');
});

it('treats band edges as inclusive', function (int $grams, string $band): void {
    expect(quote(ksaRule(), [new QuoteLine('a', $grams)], city: 102)->bandName)->toBe($band);
})->with([[0, 'Light'], [1000, 'Light'], [1001, 'Medium'], [5000, 'Medium'], [5001, 'Heavy'], [30000, 'Heavy']]);

it('ships free when no band matches the weight', function (): void {
    $result = quote(ksaRule(), [new QuoteLine('a', 31000)], city: 101);

    expect($result->available)->toBeTrue()->and($result->isFree())->toBeTrue()->and($result->bandId)->toBeNull()
        ->and($result->lineFees['a']->isZero())->toBeTrue();
});

it('uses the whole-country zone for unlisted cities and the first zone without a city', function (): void {
    expect(quote(ksaRule(), [new QuoteLine('a', 500)], city: 999)->zoneName)->toBe('Rest of KSA')
        ->and(quote(ksaRule(), [new QuoteLine('a', 500)])->zoneName)->toBe('Rest of KSA')
        ->and(quote(ksaRule(), [new QuoteLine('a', 500)], zone: 2)->amount->toDecimal())->toBe('4.50');

    $cityOnly = new RuleDefinition('Cities', [new ZoneDefinition('Riyadh', [101], [new BandDefinition('All', 0, null, Money::of(3))], 5)]);
    expect(quote($cityOnly, [new QuoteLine('a', 500)])->zoneId)->toBe(5);
});

it('is unavailable without a country, without a rule, or without a zone', function (): void {
    expect(quote(ksaRule(), [new QuoteLine('a', 500)], country: null)->reason)->toBe(ShippingUnavailableReason::CountryRequired)
        ->and(quote(null, [new QuoteLine('a', 500)])->reason)->toBe(ShippingUnavailableReason::CountryNotServed);

    $cityOnly = new RuleDefinition('Cities', [new ZoneDefinition('Riyadh', [101], [new BandDefinition('All', 0, null, Money::of(3))])]);
    $noZone = quote($cityOnly, [new QuoteLine('a', 500)], city: 555);

    expect($noZone->available)->toBeFalse()
        ->and($noZone->reason)->toBe(ShippingUnavailableReason::NoZone)
        ->and($noZone->amount->isZero())->toBeTrue()
        ->and(quote(ksaRule(), [new QuoteLine('a', 500)], zone: 99)->reason)->toBe(ShippingUnavailableReason::NoZone);
});

it('lets own products ship free and skip every availability check', function (): void {
    $ownOnly = quote(null, [new QuoteLine('own', 2000, 3, shippable: false)], country: null);

    expect($ownOnly->available)->toBeTrue()->and($ownOnly->amount->isZero())->toBeTrue()->and($ownOnly->lineWeights)->toBe(['own' => 0]);

    $mixed = quote(ksaRule(), [new QuoteLine('own', 9000, 1, shippable: false), new QuoteLine('cat', 600, 1)], city: 101);

    expect($mixed->totalWeightGrams)->toBe(600)->and($mixed->amount->toDecimal())->toBe('5.00')
        ->and($mixed->lineFees['own']->isZero())->toBeTrue()->and($mixed->lineFees['cat']->toDecimal())->toBe('5.00');
});

it('golden: splits the charge by line weight, rounding each share and giving the remainder to the last line', function (): void {
    $rule = new RuleDefinition('R', [new ZoneDefinition('Z', [], [new BandDefinition('All', 0, null, Money::of(10))])]);
    $result = quote($rule, [new QuoteLine('a', 100), new QuoteLine('b', 100), new QuoteLine('c', 100)]);

    // old: round(10 × 1/3, 2) = 3.33, 3.33, remainder 3.34
    expect(array_map(fn (Money $fee) => $fee->toDecimal(), $result->lineFees))->toBe(['a' => '3.33', 'b' => '3.33', 'c' => '3.34']);

    $weightless = quote($rule, [new QuoteLine('a', 0, 1), new QuoteLine('b', 0, 3)]);
    expect(array_map(fn (Money $fee) => $fee->toDecimal(), $weightless->lineFees))->toBe(['a' => '2.50', 'b' => '7.50']);
});

it('detects overlapping bands', function (): void {
    $zone = new ZoneDefinition('Eastern', [], [
        new BandDefinition('Light', 0, 1000, Money::zero()),
        new BandDefinition('Medium', 900, 5000, Money::zero()),
        new BandDefinition('Heavy', 5001, null, Money::zero()),
        new BandDefinition('Huge', 40000, null, Money::zero()),
    ]);

    expect($zone->overlappingBands())->toBe([[0, 1], [2, 3]])
        ->and((new ZoneDefinition('ok', [], [new BandDefinition('a', 0, 1000, Money::zero()), new BandDefinition('b', 1001, null, Money::zero())]))->overlappingBands())->toBe([]);
});
