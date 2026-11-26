<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<StoreTranslationRun>
 */
class StoreTranslationRunRepository extends BaseRepository implements StoreTranslationRunRepositoryContract
{
    protected function model(): string
    {
        return StoreTranslationRun::class;
    }

    public function forPurchase(int $addonPurchaseId): ?StoreTranslationRun
    {
        return $this->query()->withoutGlobalScopes()->where('addon_purchase_id', $addonPurchaseId)->first();
    }

    public function latestByLanguage(int $tenantId): Collection
    {
        /** @var Collection<int, StoreTranslationRun> $runs */
        $runs = $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get();

        return $runs->keyBy('language_code');
    }

    public function latestCompletedOfActiveStores(): Collection
    {
        $latest = $this->query()->withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('tenant_id', 'language_code');

        /** @var Collection<int, StoreTranslationRun> $runs */
        $runs = $this->query()->withoutGlobalScopes()
            ->whereIn('id', $latest)
            ->where('status', TranslationRunStatus::Completed->value)
            ->whereIn('tenant_id', Tenant::query()->select('id')->where('status', TenantStatus::Active->value))
            ->orderBy('tenant_id')
            ->orderBy('language_code')
            ->get();

        return $runs;
    }

    public function findForTenant(int $tenantId, int $runId): ?StoreTranslationRun
    {
        return $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($runId)->first();
    }

    public function statsForTenant(int $tenantId): array
    {
        $row = $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->toBase()
            ->selectRaw('count(*) as runs')
            ->selectRaw('coalesce(sum(case when created_at >= ? then 1 else 0 end), 0) as this_month', [now()->startOfMonth()])
            ->selectRaw('coalesce(sum(case when status in (?, ?) then 1 else 0 end), 0) as active', [TranslationRunStatus::Queued->value, TranslationRunStatus::Translating->value])
            ->selectRaw('coalesce(sum(done_items), 0) as items')
            ->selectRaw('count(distinct case when done_items > 0 then language_code end) as item_languages')
            ->selectRaw('coalesce(sum(prompt_tokens + completion_tokens), 0) as tokens')
            ->selectRaw('count(distinct case when addon_purchase_id is not null then language_code end) as paid_languages')
            ->first();

        return [
            'runs' => (int) ($row->runs ?? 0),
            'this_month' => (int) ($row->this_month ?? 0),
            'active' => (int) ($row->active ?? 0),
            'items' => (int) ($row->items ?? 0),
            'item_languages' => (int) ($row->item_languages ?? 0),
            'tokens' => (int) ($row->tokens ?? 0),
            'paid_languages' => (int) ($row->paid_languages ?? 0),
        ];
    }

    public function totalsByLanguage(int $tenantId): array
    {
        $rows = $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->toBase()
            ->select('language_code')
            ->selectRaw('count(*) as runs, coalesce(sum(done_items), 0) as items, coalesce(sum(prompt_tokens + completion_tokens), 0) as tokens')
            ->groupBy('language_code')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->language_code] = ['runs' => (int) $row->runs, 'items' => (int) $row->items, 'tokens' => (int) $row->tokens];
        }

        return $totals;
    }

    public function currentForTenant(int $tenantId): ?StoreTranslationRun
    {
        $query = fn () => $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId);

        return $query()->where('status', TranslationRunStatus::Translating->value)->orderBy('id')->first()
            ?? $query()->where('status', TranslationRunStatus::Queued->value)->orderBy('id')->first()
            ?? $query()->orderByDesc('id')->first();
    }

    public function historyRows(int $tenantId): QueryBuilder
    {
        return $this->query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->toBase()
            ->select([
                DB::raw("'run' as kind"), 'id', 'tenant_id', 'language_code', 'trigger', 'status', 'done_items', 'failed_items',
                DB::raw('(prompt_tokens + completion_tokens) as tokens'), 'started_at', 'finished_at',
                DB::raw('null as subject'), DB::raw('coalesce(started_at, queued_at, created_at) as sort_at'),
            ]);
    }

    public function historyListing(QueryBuilder $rows): Builder
    {
        return $this->query()->withoutGlobalScopes()->fromSub($rows, 'store_translation_runs');
    }
}
