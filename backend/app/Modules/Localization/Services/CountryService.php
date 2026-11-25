<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\DTOs\CountryData;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;

/**
 * Central ISO countries: the admin edits names, phone code, default currency and region,
 * and switches countries on/off (inactive countries disappear from stores and checkout).
 */
class CountryService
{
    public function __construct(private readonly CountryRepositoryContract $countries) {}

    /**
     * Tab counters of the admin list.
     *
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return [
            'active' => $this->countries->query()->where('is_active', true)->count(),
            'all' => $this->countries->query()->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->countries->options();
    }

    public function update(Country $country, CountryData $data): Country
    {
        return $this->countries->update($country, [
            'name' => array_filter($data->name, static fn (?string $value): bool => $value !== null && $value !== ''),
            'phone_code' => $data->phoneCode !== null ? ltrim($data->phoneCode, '+') : null,
            'currency_code' => $data->currencyCode !== null ? strtoupper($data->currencyCode) : null,
            'continent' => $data->continent,
            'is_active' => $data->isActive,
        ]);
    }

    public function setActive(Country $country, bool $active): Country
    {
        return $this->countries->update($country, ['is_active' => $active]);
    }
}
