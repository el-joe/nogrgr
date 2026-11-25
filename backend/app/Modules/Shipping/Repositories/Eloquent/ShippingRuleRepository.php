<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Models\ShippingZoneBand;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Modules\Shipping\Support\BandDefinition;
use App\Modules\Shipping\Support\RuleDefinition;
use App\Modules\Shipping\Support\ZoneDefinition;
use App\Shared\Money\Money;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ShippingRule>
 */
class ShippingRuleRepository extends BaseRepository implements ShippingRuleRepositoryContract
{
    protected function model(): string
    {
        return ShippingRule::class;
    }

    public function definitionForCountry(int $countryId): ?RuleDefinition
    {
        $rule = $this->query()
            ->where('status', ShippingRuleStatus::Active)
            ->whereHas('countries', static fn (Builder $query) => $query->whereKey($countryId))
            ->with(['zones.bands', 'zones.cities:id'])
            ->orderBy('id')
            ->first();

        if ($rule === null) {
            return null;
        }

        return new RuleDefinition(
            name: $rule->name,
            zones: $rule->zones->map(static fn (ShippingZone $zone): ZoneDefinition => new ZoneDefinition(
                name: $zone->name,
                cityIds: $zone->cities->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all(),
                bands: $zone->bands->map(static fn (ShippingZoneBand $band): BandDefinition => new BandDefinition(
                    $band->name,
                    $band->min_weight_grams,
                    $band->max_weight_grams,
                    $band->price,
                    $band->id,
                ))->values()->all(),
                id: $zone->id,
            ))->values()->all(),
            id: $rule->id,
        );
    }

    public function activeCountryOwners(array $countryIds, ?int $exceptRuleId = null): array
    {
        if ($countryIds === []) {
            return [];
        }

        $owners = [];

        $this->query()
            ->where('status', ShippingRuleStatus::Active)
            ->when($exceptRuleId !== null, static fn (Builder $query) => $query->whereKeyNot($exceptRuleId))
            ->with(['countries' => static fn ($query) => $query->whereIn('countries.id', $countryIds)])
            ->get()
            ->each(function (ShippingRule $rule) use (&$owners): void {
                foreach ($rule->countries as $country) {
                    $owners[(int) $country->id] = $rule->name;
                }
            });

        return $owners;
    }

    public function syncCountries(ShippingRule $rule, array $countryIds): void
    {
        $rule->countries()->sync($countryIds);
    }

    public function syncZones(ShippingRule $rule, array $zones): void
    {
        $kept = [];

        foreach (array_values($zones) as $position => $data) {
            /** @var ShippingZone|null $zone */
            $zone = $data['id'] !== null ? $rule->zones()->whereKey($data['id'])->first() : null;
            $zone ??= new ShippingZone(['shipping_rule_id' => $rule->id]);
            $zone->fill(['name' => $data['name'], 'position' => $position])->save();
            $zone->cities()->sync($data['city_ids']);
            $zone->bands()->delete();

            foreach (array_values($data['bands']) as $bandPosition => $band) {
                $zone->bands()->create([...$band, 'price' => Money::ofMinor($band['price']), 'position' => $bandPosition]);
            }

            $kept[] = $zone->id;
        }

        ShippingZone::query()->where('shipping_rule_id', $rule->id)->whereNotIn('id', $kept)->delete();
    }

    public function zonesForCountry(int $countryId, ?string $search = null): Collection
    {
        return ShippingZone::query()
            ->with('rule')
            ->whereHas('rule', static fn (Builder $query) => $query->whereHas('countries', static fn (Builder $query) => $query->whereKey($countryId)))
            ->when($search !== null && $search !== '', static fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->orderBy('shipping_rule_id')
            ->orderBy('position')
            ->limit(50)
            ->get();
    }

    public function counts(): array
    {
        return [
            'active' => $this->query()->where('status', ShippingRuleStatus::Active)->count(),
            'all' => $this->query()->count(),
        ];
    }
}
