<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\DTOs\DayRuleData;
use App\Modules\Shipping\DTOs\ShippingCompanyData;
use App\Modules\Shipping\Models\ShippingCompany;
use App\Modules\Shipping\Models\ShippingDayRule;
use App\Modules\Shipping\Repositories\Contracts\ShippingCompanyRepositoryContract;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Shipping companies (ADM-033 / 033a / 033b): details + logo (public disk), active toggle,
 * day rules per country (unique per company) and the default company per country.
 */
class ShippingCompanyService extends BaseService
{
    public const LOGO_DISK = 'public';

    public function __construct(private readonly ShippingCompanyRepositoryContract $companies) {}

    /**
     * @return array<int, string>
     */
    public function activeOptions(): array
    {
        return $this->companies->activeOptions();
    }

    /**
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return $this->companies->counts();
    }

    public function create(ShippingCompanyData $data): ShippingCompany
    {
        $company = $this->transaction(fn (): ShippingCompany => $this->companies->create($this->columns($data)), $this->connection());
        $this->storeLogo($company, $data);

        return $company;
    }

    public function update(ShippingCompany $company, ShippingCompanyData $data): ShippingCompany
    {
        $this->transaction(fn () => $this->companies->update($company, $this->columns($data)), $this->connection());
        $this->storeLogo($company, $data);

        return $company;
    }

    public function setActive(ShippingCompany $company, bool $active): void
    {
        $this->companies->update($company, ['is_active' => $active]);
    }

    public function delete(ShippingCompany $company): void
    {
        $logo = $company->logo_path;
        $this->transaction(fn () => $this->companies->delete($company), $this->connection());

        if ($logo !== null) {
            Storage::disk(self::LOGO_DISK)->delete($logo);
        }
    }

    /**
     * @return Collection<int, ShippingDayRule>
     */
    public function dayRules(ShippingCompany $company): Collection
    {
        return $this->companies->dayRules($company);
    }

    /**
     * @return list<int>
     */
    public function defaultCountryIds(ShippingCompany $company): array
    {
        return $this->companies->defaultCountryIds($company);
    }

    public function findDayRule(ShippingCompany $company, int $id): ShippingDayRule
    {
        return $this->companies->findDayRule($company, $id) ?? abort(404);
    }

    public function saveDayRule(ShippingCompany $company, DayRuleData $data, ?ShippingDayRule $rule = null): ShippingDayRule
    {
        $existing = $this->companies->dayRule($company->id, $data->countryId);

        if ($existing !== null && $existing->id !== $rule?->id) {
            throw new DomainException(__('shipping::companies.day_rules.duplicate'));
        }

        return $this->transaction(function () use ($company, $data, $rule): ShippingDayRule {
            $previousCountry = $rule?->country_id;
            $saved = $this->companies->saveDayRule($company, ['country_id' => $data->countryId, 'min_days' => $data->minDays, 'max_days' => $data->maxDays], $rule);

            if ($previousCountry !== null && $previousCountry !== $data->countryId && $this->companies->defaultCompanyId($previousCountry) === $company->id) {
                $this->companies->setDefault($previousCountry, null);
            }

            if ($data->isDefault) {
                $this->companies->setDefault($data->countryId, $company->id);
            } elseif ($this->companies->defaultCompanyId($data->countryId) === $company->id) {
                $this->companies->setDefault($data->countryId, null);
            }

            return $saved;
        }, $this->connection());
    }

    public function deleteDayRule(ShippingCompany $company, ShippingDayRule $rule): void
    {
        $this->transaction(function () use ($company, $rule): void {
            if ($this->companies->defaultCompanyId($rule->country_id) === $company->id) {
                $this->companies->setDefault($rule->country_id, null);
            }

            $this->companies->deleteDayRule($rule);
        }, $this->connection());
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(ShippingCompanyData $data): array
    {
        return [
            'name' => $data->name,
            'contact_email' => $data->contactEmail,
            'contact_phone' => $data->contactPhone,
            'website' => $data->website,
            'tracking_url_pattern' => $data->trackingUrlPattern,
            'is_active' => $data->isActive,
        ];
    }

    private function storeLogo(ShippingCompany $company, ShippingCompanyData $data): void
    {
        $old = $company->logo_path;

        if ($data->logo !== null) {
            $path = $data->logo->store('shipping/companies', self::LOGO_DISK);
            $this->companies->update($company, ['logo_path' => $path === false ? null : $path]);
        } elseif (! $data->keepLogo) {
            $this->companies->update($company, ['logo_path' => null]);
        } else {
            return;
        }

        if ($old !== null && $old !== $company->logo_path) {
            Storage::disk(self::LOGO_DISK)->delete($old);
        }
    }

    private function connection(): ?string
    {
        return (new ShippingCompany)->getConnectionName();
    }
}
