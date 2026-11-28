<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\DTOs\StoreCurrencySettingsData;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shopper-facing currencies of the current store (VEN-072, Figma Vendor Panel 90:112623).
 * Display only: every amount is stored and charged in USD (decision #87); rates are the
 * platform's (central `currencies`, synced daily). The store lists every currency the platform
 * has switched on (decision #133) and decides per currency:
 *   active   offered in the storefront switcher (USD always)
 *   default  shown automatically to shoppers of its selling countries that use it
 *            (otherwise they see USD); only currencies of selling countries have it
 * Stored in tenant `store_settings` (StoreSettingKey::Currencies) as the exceptions
 * {"inactive": [code], "not_default": [code]}, so new currencies start on.
 */
class StoreCurrencyService extends BaseService
{
    /**
     * Stored exceptions per tenant key (the service may outlive one tenant context).
     *
     * @var array<string, array{inactive: list<string>, not_default: list<string>}>
     */
    private array $stored = [];

    public function __construct(
        private readonly CurrencyRepositoryContract $currencies,
        private readonly CountryRepositoryContract $countries,
        private readonly StoreSettingRepositoryContract $settings,
        private readonly Auditor $auditor,
    ) {}

    /**
     * Rows of the settings table: every platform-active currency (by code), then USD.
     *
     * @return list<array{currency: Currency, countries: list<string>, is_base: bool, active: bool, default: bool}>
     */
    public function rows(): array
    {
        $byCode = $this->countriesByCurrency();
        $stored = $this->stored();
        $rows = [];

        foreach ($this->currencies->active() as $currency) {
            /** @var Currency $currency */
            $base = $currency->isBase();

            $active = $base || ! in_array($currency->code, $stored['inactive'], true);
            $rows[] = [
                'currency' => $currency,
                'countries' => $base || ! isset($byCode[$currency->code]) ? [] : $byCode[$currency->code]->map(static fn (Country $country): string => (string) $country->name)->values()->all(),
                'is_base' => $base,
                'active' => $active,
                'default' => ! $base && $active && isset($byCode[$currency->code]) && ! in_array($currency->code, $stored['not_default'], true),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['is_base'] ? 1 : 0, $a['currency']->code] <=> [$b['is_base'] ? 1 : 0, $b['currency']->code]);

        return $rows;
    }

    /**
     * Codes the store may switch (non-base rows).
     *
     * @return list<string>
     */
    public function configurableCodes(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['currency']->code, array_filter($this->rows(), static fn (array $row): bool => ! $row['is_base'])));
    }

    public function save(StoreCurrencySettingsData $data): void
    {
        $codes = $this->configurableCodes();
        $active = array_values(array_intersect($codes, $data->active));
        $default = array_values(array_intersect($active, $data->default));
        $before = $this->stored();
        $value = [
            'inactive' => array_values(array_diff($codes, $active)),
            'not_default' => array_values(array_diff($active, $default)),
        ];

        $this->settings->put(StoreSettingKey::Currencies, $value);
        $this->stored[$this->tenantKey()] = $value;

        if ($before !== $value) {
            $this->auditor->record('store.currencies_updated', null, ['before' => $before, 'after' => $value]);
        }
    }

    /**
     * Currencies of the storefront switcher: USD plus the store's active currencies.
     * Outside a store (central pages) every platform currency.
     *
     * @return Collection<int, Currency>
     */
    public function switcherCurrencies(): Collection
    {
        if (! $this->inStore()) {
            return collect($this->currencies->active()->all())->filter(static fn (Currency $currency): bool => $currency->hasUsableRate())->values();
        }

        return collect($this->rows())->filter(static fn (array $row): bool => $row['active'] && $row['currency']->hasUsableRate())->map(static fn (array $row): Currency => $row['currency'])->values();
    }

    /**
     * May a shopper of this store pick the currency in the switcher?
     */
    public function isAvailable(Currency $currency): bool
    {
        if ($currency->isBase()) {
            return true;
        }

        // Never-synced rate (QA-FINAL-009): hidden from shoppers until the sync ran.
        if (! $currency->is_active || ! $currency->hasUsableRate()) {
            return false;
        }

        return ! $this->inStore() || ! in_array($currency->code, $this->stored()['inactive'], true);
    }

    /**
     * Is the currency shown automatically to shoppers of its countries?
     */
    public function isCountryDefault(Currency $currency): bool
    {
        return $this->isAvailable($currency) && (! $this->inStore() || ! in_array($currency->code, $this->stored()['not_default'], true));
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->currencies->lastSyncedAt();
    }

    /**
     * @return array<string, Collection<int, Country>>
     */
    private function countriesByCurrency(): array
    {
        $tenant = $this->inStore() ? tenant() : null;
        $ids = $tenant instanceof Tenant ? array_values(array_map('intval', (array) $tenant->selling_country_ids)) : [];

        if ($ids === []) {
            return [];
        }

        $grouped = [];

        foreach ($this->countries->active($ids)->sortBy('name') as $country) {
            /** @var Country $country */
            $code = strtoupper((string) $country->currency_code);

            if ($code === '' || $code === Money::BASE_CURRENCY) {
                continue;
            }

            $grouped[$code] ??= collect();
            $grouped[$code]->push($country);
        }

        return $grouped;
    }

    /**
     * @return array{inactive: list<string>, not_default: list<string>}
     */
    private function stored(): array
    {
        $key = $this->tenantKey();

        if (isset($this->stored[$key])) {
            return $this->stored[$key];
        }

        $value = $this->inStore() ? $this->settings->get(StoreSettingKey::Currencies) : null;
        $value = is_array($value) ? $value : [];
        $list = static fn (mixed $codes): array => array_values(array_filter(array_map(static fn (mixed $code): string => strtoupper((string) $code), is_array($codes) ? $codes : []), static fn (string $code): bool => preg_match('/^[A-Z]{3}$/', $code) === 1));

        return $this->stored[$key] = ['inactive' => $list($value['inactive'] ?? []), 'not_default' => $list($value['not_default'] ?? [])];
    }

    private function tenantKey(): string
    {
        return $this->inStore() ? (string) tenant()?->getTenantKey() : 'central';
    }

    private function inStore(): bool
    {
        return tenancy()->initialized && tenant() instanceof Tenant;
    }
}
