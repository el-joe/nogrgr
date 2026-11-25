<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<ShippingCompany>
 */
class ShippingCompanyRepository extends BaseRepository implements ShippingCompanyRepositoryContract
{
    protected function model(): string
    {
        return ShippingCompany::class;
    }

    public function activeOptions(): array
    {
        /** @var array<int, string> $options */
        $options = $this->query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }

    public function counts(): array
    {
        return [
            'active' => $this->query()->where('is_active', true)->count(),
            'all' => $this->query()->count(),
        ];
    }

    public function dayRule(int $companyId, int $countryId): ?ShippingDayRule
    {
        return ShippingDayRule::query()->where('shipping_company_id', $companyId)->where('country_id', $countryId)->first();
    }

    public function findDayRule(ShippingCompany $company, int $dayRuleId): ?ShippingDayRule
    {
        return $company->dayRules()->whereKey($dayRuleId)->first();
    }

    public function saveDayRule(ShippingCompany $company, array $attributes, ?ShippingDayRule $rule = null): ShippingDayRule
    {
        $rule ??= new ShippingDayRule(['shipping_company_id' => $company->id]);
        $rule->fill($attributes)->save();

        return $rule;
    }

    public function deleteDayRule(ShippingDayRule $rule): void
    {
        $rule->delete();
    }

    public function defaultCompanyId(int $countryId): ?int
    {
        $id = $this->defaults()->where('country_id', $countryId)->value('shipping_company_id');

        return $id === null ? null : (int) $id;
    }

    public function setDefault(int $countryId, ?int $companyId): void
    {
        if ($companyId === null) {
            $this->defaults()->where('country_id', $countryId)->delete();

            return;
        }

        $this->defaults()->updateOrInsert(['country_id' => $countryId], ['shipping_company_id' => $companyId]);
    }

    public function defaultCountryIds(ShippingCompany $company): array
    {
        return $this->defaults()->where('shipping_company_id', $company->id)->pluck('country_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function defaultsTable(): array
    {
        return $this->defaults()
            ->join('shipping_companies', 'shipping_companies.id', '=', 'shipping_default_companies.shipping_company_id')
            ->leftJoin('shipping_day_rules', function ($join): void {
                $join->on('shipping_day_rules.shipping_company_id', '=', 'shipping_default_companies.shipping_company_id')
                    ->on('shipping_day_rules.country_id', '=', 'shipping_default_companies.country_id');
            })
            ->orderBy('shipping_default_companies.country_id')
            ->get(['shipping_default_companies.country_id', 'shipping_companies.id as company_id', 'shipping_companies.name as company', 'shipping_day_rules.min_days', 'shipping_day_rules.max_days'])
            ->map(static fn (object $row): array => [
                'country_id' => (int) $row->country_id,
                'company_id' => (int) $row->company_id,
                'company' => (string) $row->company,
                'min_days' => $row->min_days === null ? null : (int) $row->min_days,
                'max_days' => $row->max_days === null ? null : (int) $row->max_days,
            ])
            ->values()
            ->all();
    }

    public function dayRules(ShippingCompany $company): Collection
    {
        return $company->dayRules()->with('country')->orderBy('country_id')->get();
    }

    private function defaults(): QueryBuilder
    {
        return DB::connection((new ShippingCompany)->getConnectionName())->table('shipping_default_companies');
    }
}
