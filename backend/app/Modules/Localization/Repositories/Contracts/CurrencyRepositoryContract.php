<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\Currency;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends RepositoryContract<Currency>
 */
interface CurrencyRepositoryContract extends RepositoryContract
{
    public function findByCode(string $code): ?Currency;

    /**
     * @return Collection<int, Currency>
     */
    public function active(): Collection;

    /**
     * code => label pairs for selects (all currencies).
     *
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * Write new rates (code => rate) without touching other columns; returns rows updated.
     * The base currency is never changed.
     *
     * @param  array<string, float|int|string>  $rates
     */
    public function updateRates(array $rates, Carbon $syncedAt): int;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;

    public function lastSyncedAt(): ?Carbon;
}
