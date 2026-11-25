<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\DTOs\ShippingRuleData;
use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Modules\Shipping\Models\ShippingRule;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Shipping\Repositories\Contracts\ShippingRuleRepositoryContract;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shipping rules / zones (ADM-032 / 032a): a country belongs to at most one ACTIVE rule
 * (drafts may overlap), zones replace on save, band overlap is rejected by the request.
 */
class ShippingRuleService extends BaseService
{
    public function __construct(private readonly ShippingRuleRepositoryContract $rules) {}

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return $this->rules->counts();
    }

    public function create(ShippingRuleData $data): ShippingRule
    {
        $this->guardCountries($data, null);

        return $this->transaction(function () use ($data): ShippingRule {
            $rule = $this->rules->create(['name' => $data->name, 'status' => $data->status]);
            $this->syncChildren($rule, $data);

            return $rule;
        }, $this->connection());
    }

    public function update(ShippingRule $rule, ShippingRuleData $data): ShippingRule
    {
        $this->guardCountries($data, $rule->id);

        return $this->transaction(function () use ($rule, $data): ShippingRule {
            $this->rules->update($rule, ['name' => $data->name, 'status' => $data->status]);
            $this->syncChildren($rule, $data);

            return $rule;
        }, $this->connection());
    }

    public function delete(ShippingRule $rule): void
    {
        $this->transaction(fn () => $this->rules->delete($rule), $this->connection());
    }

    /**
     * @return Collection<int, ShippingZone>
     */
    public function zonesForCountry(int $countryId, ?string $search = null): Collection
    {
        return $this->rules->zonesForCountry($countryId, $search);
    }

    private function guardCountries(ShippingRuleData $data, ?int $ruleId): void
    {
        if ($data->status !== ShippingRuleStatus::Active) {
            return;
        }

        $owners = $this->rules->activeCountryOwners($data->countryIds, $ruleId);

        if ($owners !== []) {
            $countryId = array_key_first($owners);
            $country = Country::query()->find($countryId);

            throw new DomainException(__('shipping::rules.country_taken', ['country' => $country->name ?? (string) $countryId, 'rule' => $owners[$countryId]]));
        }
    }

    private function syncChildren(ShippingRule $rule, ShippingRuleData $data): void
    {
        $this->rules->syncCountries($rule, array_values(array_unique($data->countryIds)));
        $this->rules->syncZones($rule, $data->zones);
    }

    private function connection(): ?string
    {
        return (new ShippingRule)->getConnectionName();
    }
}
