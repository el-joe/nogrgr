<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\StoreTranslationRun;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Central DB, tenant scoped (current store) unless queried without global scopes.
 *
 * @extends RepositoryContract<StoreTranslationRun>
 */
interface StoreTranslationRunRepositoryContract extends RepositoryContract
{
    public function forPurchase(int $addonPurchaseId): ?StoreTranslationRun;

    /**
     * Latest run per language code of one store.
     *
     * @return Collection<string, StoreTranslationRun>
     */
    public function latestByLanguage(int $tenantId): Collection;

    /**
     * Every store × language whose latest run completed, for active stores only (weekly
     * delta auto-translation).
     *
     * @return Collection<int, StoreTranslationRun>
     */
    public function latestCompletedOfActiveStores(): Collection;

    public function findForTenant(int $tenantId, int $runId): ?StoreTranslationRun;

    /**
     * Run counters of one store (VEN-074 KPI cards): runs, runs started this month,
     * active runs, items translated, languages with translated items, tokens.
     *
     * @return array{runs: int, this_month: int, active: int, items: int, item_languages: int, tokens: int, paid_languages: int}
     */
    public function statsForTenant(int $tenantId): array;

    /**
     * Totals per language code of one store: runs, items translated, tokens.
     *
     * @return array<string, array{runs: int, items: int, tokens: int}>
     */
    public function totalsByLanguage(int $tenantId): array;

    /**
     * The run to follow live: the oldest translating run, else the oldest queued one,
     * else the latest run of the store.
     */
    public function currentForTenant(int $tenantId): ?StoreTranslationRun;

    /**
     * History rows of one store in the run-history shape (kind, id, tenant_id,
     * language_code, trigger, status, done_items, failed_items, tokens, started_at,
     * finished_at, subject, sort_at), for a union with other AI translation rows.
     */
    public function historyRows(int $tenantId): QueryBuilder;

    /**
     * Eloquent listing over a union of history rows (see historyRows()).
     *
     * @return Builder<StoreTranslationRun>
     */
    public function historyListing(QueryBuilder $rows): Builder;
}
