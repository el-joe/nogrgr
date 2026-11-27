<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\DTOs\CurrencyData;
use App\Modules\Localization\Exceptions\LockedRecordException;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\Money\Money;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Central currencies. USD is the base (rate 1): it is always active, its code and rate
 * never change and it cannot be deleted. Rates (units per 1 USD) come from
 * open.er-api.com through the daily SyncExchangeRates job or the admin "Sync now" button.
 */
class CurrencyService
{
    public const RATES_URL = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Daily schedule time (app timezone) of the SyncExchangeRates job.
     */
    public const SYNC_AT = '06:00';

    public function __construct(
        private readonly CurrencyRepositoryContract $currencies,
        private readonly CountryRepositoryContract $countries,
    ) {}

    public function create(CurrencyData $data): Currency
    {
        return $this->currencies->create($this->attributes($data) + [
            'code' => strtoupper($data->code),
            'is_base' => false,
            'rate' => $this->rate($data->rate),
        ]);
    }

    public function update(Currency $currency, CurrencyData $data): Currency
    {
        if ($currency->isBase()) {
            if (! $data->isActive) {
                throw LockedRecordException::baseCurrency();
            }

            return $this->currencies->update($currency, $this->attributes($data));
        }

        $attributes = $this->attributes($data);

        if ($data->rate !== null && $data->rate !== '') {
            $attributes['rate'] = $this->rate($data->rate);
        }

        return $this->currencies->update($currency, $attributes);
    }

    public function setActive(Currency $currency, bool $active): Currency
    {
        if ($currency->isBase() && ! $active) {
            throw LockedRecordException::baseCurrency();
        }

        return $this->currencies->update($currency, ['is_active' => $active]);
    }

    public function delete(Currency $currency): void
    {
        if ($currency->isBase()) {
            throw LockedRecordException::baseCurrency();
        }

        $used = $this->countries->countUsingCurrency($currency->code);

        if ($used > 0) {
            throw new DomainException(trans_choice('localization::admin.currencies.in_use', $used, ['count' => $used]));
        }

        $this->currencies->delete($currency);
    }

    /**
     * Pull the latest USD rates and store them; returns how many currencies changed.
     *
     * @throws RuntimeException when the provider answers with an error or an unexpected payload
     */
    public function syncRates(): int
    {
        $response = Http::acceptJson()->timeout(15)->retry(2, 500, throw: false)->get(self::RATES_URL);

        if ($response->failed()) {
            throw new RuntimeException('Exchange rate provider answered HTTP '.$response->status().'.');
        }

        $rates = $response->json('rates');

        if ($response->json('result') !== 'success' || ! is_array($rates)) {
            throw new RuntimeException('Exchange rate provider returned an unexpected payload.');
        }

        $valid = array_filter($rates, static fn (mixed $rate, mixed $code): bool => is_string($code) && is_numeric($rate) && (float) $rate > 0, ARRAY_FILTER_USE_BOTH);

        return $this->currencies->updateRates($valid, now());
    }

    /**
     * code => label pairs for selects.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->currencies->options();
    }

    /**
     * Tab counters of the admin list.
     *
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return [
            'active' => $this->currencies->query()->where('is_active', true)->count(),
            'all' => $this->currencies->query()->count(),
        ];
    }

    /**
     * Active non-base currencies whose rate was never synced (QA-FINAL-009): shoppers do
     * not see them until the sync runs; the admin list warns about them.
     *
     * @return list<string> currency codes
     */
    public function unsyncedActiveCodes(): array
    {
        return $this->currencies->query()
            ->where('is_active', true)
            ->where('is_base', false)
            ->where('code', '!=', Money::BASE_CURRENCY)
            ->whereNull('rate_synced_at')
            ->orderBy('code')
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->all();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->currencies->lastSyncedAt();
    }

    public function nextSyncAt(): Carbon
    {
        $next = now()->setTimeFromTimeString(self::SYNC_AT);

        return $next->isPast() ? $next->addDay() : $next;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CurrencyData $data): array
    {
        return [
            'name' => array_filter($data->name, static fn (?string $value): bool => $value !== null && $value !== ''),
            'symbol' => $data->symbol,
            'decimals' => $data->decimals,
            'is_active' => $data->isActive,
            'sort_order' => $data->sortOrder,
        ];
    }

    private function rate(?string $rate): string
    {
        return number_format((float) ($rate ?: 1), 6, '.', '');
    }
}
