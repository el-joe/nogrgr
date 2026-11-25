<?php

use App\Modules\Catalog\Enums\CountryAvailability;
use App\Modules\Catalog\Support\VariantMatrix;

it('builds every combination of the chosen options', function (): void {
    expect(VariantMatrix::combinations([1 => [10, 11], 2 => [20, 21, 22]]))->toHaveCount(6)
        ->and(VariantMatrix::combinations([1 => [10, 11], 2 => [20]]))->toBe([[10, 20], [11, 20]])
        ->and(VariantMatrix::combinations([1 => [], 2 => []]))->toBe([])
        ->and(VariantMatrix::combinations([1 => [10, 10]]))->toBe([[10]]);
});

it('keys a combination by its sorted option ids', function (): void {
    expect(VariantMatrix::key([22, 3, '10']))->toBe('3-10-22')
        ->and(VariantMatrix::key([3, 22, 10]))->toBe(VariantMatrix::key([22, 10, 3]));
});

it('maps country availability to allowed_country_ids', function (): void {
    expect(CountryAvailability::All->toIds([1, 2]))->toBeNull()
        ->and(CountryAvailability::None->toIds([1, 2]))->toBe([])
        ->and(CountryAvailability::Selected->toIds([2, 2, 5]))->toBe([2, 5])
        ->and(CountryAvailability::fromIds(null))->toBe(CountryAvailability::All)
        ->and(CountryAvailability::fromIds([]))->toBe(CountryAvailability::None)
        ->and(CountryAvailability::fromIds([4]))->toBe(CountryAvailability::Selected);
});
