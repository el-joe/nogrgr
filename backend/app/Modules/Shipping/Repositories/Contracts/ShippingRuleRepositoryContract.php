<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Support\RuleDefinition;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<ShippingRule>
 */
interface ShippingRuleRepositoryContract extends RepositoryContract
{
    /**
     * The active rule of a country as a pure definition (null = country not served).
     */
    public function definitionForCountry(int $countryId): ?RuleDefinition;

    /**
     * Countries (of the given ids) already in another active rule: country_id => rule name.
     *
     * @param  list<int>  $countryIds
     * @return array<int, string>
     */
    public function activeCountryOwners(array $countryIds, ?int $exceptRuleId = null): array;

    /**
     * @param  list<int>  $countryIds
     */
    public function syncCountries(ShippingRule $rule, array $countryIds): void;

    /**
     * Replaces the zones, their cities and bands. Zones keep their ids when posted with one.
     *
     * @param  list<array{id: int|null, name: string, city_ids: list<int>, bands: list<array{name: string, min_weight_grams: int, max_weight_grams: int|null, price: int}>}>  $zones
     */
    public function syncZones(ShippingRule $rule, array $zones): void;

    /**
     * Zones of the active rules covering a country (branch "serves zones" picker).
     *
     * @return Collection<int, ShippingZone>
     */
    public function zonesForCountry(int $countryId, ?string $search = null): Collection;

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array;
}
