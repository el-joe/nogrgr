<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shipping companies, their day rules per country and the default company per country.
 *
 * @extends RepositoryContract<ShippingCompany>
 */
interface ShippingCompanyRepositoryContract extends RepositoryContract
{
    /**
     * @return array<int, string> id => name of active companies
     */
    public function activeOptions(): array;

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array;

    public function dayRule(int $companyId, int $countryId): ?ShippingDayRule;

    public function findDayRule(ShippingCompany $company, int $dayRuleId): ?ShippingDayRule;

    /**
     * @param  array{country_id: int, min_days: int, max_days: int}  $attributes
     */
    public function saveDayRule(ShippingCompany $company, array $attributes, ?ShippingDayRule $rule = null): ShippingDayRule;

    public function deleteDayRule(ShippingDayRule $rule): void;

    public function defaultCompanyId(int $countryId): ?int;

    /**
     * Makes $companyId the default carrier of the country (null clears it).
     */
    public function setDefault(int $countryId, ?int $companyId): void;

    /**
     * @return list<int>
     */
    public function defaultCountryIds(ShippingCompany $company): array;

    /**
     * Default company per country with its days: [{country, company, min_days, max_days}].
     *
     * @return list<array{country_id: int, company_id: int, company: string, min_days: int|null, max_days: int|null}>
     */
    public function defaultsTable(): array;

    /**
     * @return Collection<int, ShippingDayRule>
     */
    public function dayRules(ShippingCompany $company): Collection;
}
