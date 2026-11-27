<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Enums\CacheAction;
use App\Modules\Platform\Enums\CacheRunStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Jobs\RunCacheAction;
use App\Modules\Platform\Models\CacheClearRun;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\CacheClearRunRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Support\CacheClearer;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin › System tools › Cache tools (ADM-114 / 114a / 114b). Every action is recorded
 * (cache_clear_runs: last cleared at / by) and audited ("system.cache.cleared"). Fast
 * actions run in the request; actions touching every store run on the queue
 * (RunCacheAction) and finish their run row there.
 */
class CacheToolsService extends BaseService
{
    public const AUDIT_ACTION = 'system.cache.cleared';

    public function __construct(
        private readonly CacheClearRunRepositoryContract $runs,
        private readonly TenantRepositoryContract $tenants,
        private readonly CacheClearer $clearer,
        private readonly Auditor $auditor,
    ) {}

    public function run(CacheAction $action, ?Tenant $tenant, ?int $adminId): CacheClearRun
    {
        if ($action->needsStore() && $tenant === null) {
            throw new PlatformRuleException(__('platform::cache_tools.rules.store_required'));
        }

        /** @var CacheClearRun $run */
        $run = $this->runs->create([
            'action' => $action,
            'tenant_id' => $action->needsStore() ? $tenant?->getKey() : null,
            'status' => CacheRunStatus::Queued,
            'admin_id' => $adminId,
        ]);

        $this->auditor->record(self::AUDIT_ACTION, $run, array_filter([
            'action' => $action->value,
            'tenant_id' => $run->tenant_id,
            'queued' => $action->queued(),
        ], static fn ($value): bool => $value !== null));

        if ($action->queued()) {
            RunCacheAction::dispatch((int) $run->id);

            return $run;
        }

        return $this->execute($run);
    }

    /**
     * Perform a recorded run (in the request or in RunCacheAction) and finish its row.
     */
    public function execute(CacheClearRun $run): CacheClearRun
    {
        $stores = 0;
        $failures = 0;

        try {
            match ($run->action) {
                CacheAction::Application => $this->clearer->application(),
                CacheAction::ConfigRoutes => $this->clearer->configAndRoutes(),
                CacheAction::Views => $this->clearer->views(),
                CacheAction::StorePages => $this->clearer->storePages($this->store($run)),
                CacheAction::StoreTranslations => $this->clearer->storeTranslations($this->store($run)),
                CacheAction::StoreThemes => $this->clearer->storeThemes($this->store($run)),
                CacheAction::StaticTexts, CacheAction::AllStores => $this->eachStore($run->action, $stores, $failures),
            };
        } catch (Throwable $error) {
            report($error);

            /** @var CacheClearRun $run */
            $run = $this->runs->update($run, ['status' => CacheRunStatus::Failed, 'error' => Str::limit($error->getMessage(), 490, ''), 'finished_at' => now()]);

            return $run;
        }

        /** @var CacheClearRun $run */
        $run = $this->runs->update($run, [
            'status' => CacheRunStatus::Done,
            'stores' => $stores,
            'error' => $failures > 0 ? __('platform::cache_tools.partial', ['count' => $failures]) : null,
            'finished_at' => now(),
        ]);

        return $run;
    }

    /**
     * Latest run per main action (central) and, when a store is picked, per store action.
     *
     * @return array{main: array<string, CacheClearRun>, store: array<string, CacheClearRun>, all: CacheClearRun|null}
     */
    public function lastRuns(?int $tenantId): array
    {
        $main = $this->runs->latest(array_map(static fn (CacheAction $action): string => $action->value, [...CacheAction::main(), CacheAction::AllStores]));

        return [
            'main' => $main,
            'store' => $tenantId === null ? [] : $this->runs->latest(array_map(static fn (CacheAction $action): string => $action->value, CacheAction::store()), $tenantId),
            'all' => $main[CacheAction::AllStores->value] ?? null,
        ];
    }

    public function storeCount(): int
    {
        return $this->tenants->query()->count();
    }

    private function store(CacheClearRun $run): Tenant
    {
        $tenant = $run->tenant_id !== null ? $this->tenants->find($run->tenant_id) : null;

        if (! $tenant instanceof Tenant) {
            throw new PlatformRuleException(__('platform::cache_tools.rules.store_required'));
        }

        return $tenant;
    }

    private function eachStore(CacheAction $action, int &$stores, int &$failures): void
    {
        foreach ($this->tenants->query()->orderBy('id')->lazyById(100) as $tenant) {
            /** @var Tenant $tenant */
            try {
                if ($action === CacheAction::AllStores) {
                    $this->clearer->storePages($tenant);
                    $this->clearer->storeThemes($tenant);
                }

                $this->clearer->storeTranslations($tenant);
                $stores++;
            } catch (Throwable $error) {
                report($error);
                $failures++;
            }
        }
    }
}
