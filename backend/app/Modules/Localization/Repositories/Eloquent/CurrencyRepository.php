<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<Currency>
 */
class CurrencyRepository extends BaseRepository implements CurrencyRepositoryContract
{
    protected function model(): string
    {
        return Currency::class;
    }

    public function findByCode(string $code): ?Currency
    {
        return $this->query()->where('code', strtoupper($code))->first();
    }

    public function active(): Collection
    {
        return $this->query()->active()->orderBy('sort_order')->orderBy('code')->get();
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->query()->orderBy('code')->get() as $currency) {
            $options[$currency->code] = $currency->code.' — '.$currency->name;
        }

        return $options;
    }

    public function updateRates(array $rates, Carbon $syncedAt): int
    {
        $updated = 0;

        foreach ($this->query()->where('code', '!=', Money::BASE_CURRENCY)->whereIn('code', array_keys($rates))->get(['id', 'code']) as $currency) {
            $updated += $this->query()->whereKey($currency->id)->update([
                'rate' => number_format((float) $rates[$currency->code], 6, '.', ''),
                'rate_synced_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ]);
        }

        $this->query()->where('code', Money::BASE_CURRENCY)->update(['rate' => '1.000000', 'rate_synced_at' => $syncedAt]);

        return $updated;
    }

    public function upsertMany(array $rows): int
    {
        return $this->query()->upsert($rows, ['code'], ['name', 'symbol', 'decimals', 'updated_at']);
    }

    public function lastSyncedAt(): ?Carbon
    {
        $value = $this->query()->max('rate_synced_at');

        return $value !== null ? Carbon::parse((string) $value) : null;
    }
}
