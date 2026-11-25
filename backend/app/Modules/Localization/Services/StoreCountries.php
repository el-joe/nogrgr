<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CityRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Shared\Services\BaseService;
use Collator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Countries a store sells to: active central countries, limited to the tenant's
 * `selling_country_ids` (tenant data attribute, set by the Shipping/Platform settings)
 * when that list is not empty. Used by the address form (country select), the address
 * validation rule and the geocoding endpoints. City suggestions come from central `cities`.
 */
class StoreCountries extends BaseService
{
    /**
     * Memo per tenant, language and selling list (the service can outlive one request).
     *
     * @var array<string, Collection<int, Country>>
     */
    private array $available = [];

    public function __construct(
        private readonly CountryRepositoryContract $countries,
        private readonly CityRepositoryContract $cities,
    ) {}

    /**
     * Available countries sorted by their name in the current language.
     *
     * @return Collection<int, Country>
     */
    public function available(): Collection
    {
        $ids = $this->sellingCountryIds();
        $key = (tenancy()->initialized ? (string) tenant()?->getTenantKey() : 'central').'|'.app()->getLocale().'|'.implode(',', $ids ?? []);

        if (isset($this->available[$key])) {
            return $this->available[$key];
        }

        $countries = $this->countries->active($ids);
        $collator = new Collator(app()->getLocale());

        return $this->available[$key] = $countries->sort(
            static fn (Country $a, Country $b): int => (int) $collator->compare($a->name, $b->name)
        )->values();
    }

    public function isAvailable(int $countryId): bool
    {
        return $this->available()->contains('id', $countryId);
    }

    public function find(int $countryId): ?Country
    {
        return $this->available()->firstWhere('id', $countryId);
    }

    public function findByIso2(string $iso2): ?Country
    {
        return $this->available()->firstWhere('iso2', strtoupper($iso2));
    }

    /**
     * City suggestions of a country, matching the term in any language (capitals first).
     *
     * @return Collection<int, City>
     */
    public function citySuggestions(int $countryId, ?string $term = null, int $limit = 20): Collection
    {
        $cities = $this->cities->activeForCountry($countryId);
        $term = $term !== null ? trim($term) : '';

        if ($term !== '') {
            $cities = $cities->filter(static function (City $city) use ($term): bool {
                foreach ($city->getTranslations('name') as $name) {
                    if (is_string($name) && mb_stripos($name, $term) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $cities->take($limit)->values();
    }

    /**
     * Forget the per-request memo (tests, tenant switches).
     */
    public function flush(): void
    {
        $this->available = [];
    }

    /**
     * @return list<int>|null
     */
    private function sellingCountryIds(): ?array
    {
        $ids = tenancy()->initialized ? tenant('selling_country_ids') : null;

        if (! is_array($ids) || $ids === []) {
            return null;
        }

        return array_values(array_map('intval', $ids));
    }
}
