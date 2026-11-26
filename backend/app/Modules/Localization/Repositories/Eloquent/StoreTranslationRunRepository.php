<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Enums\TranslationRunStatus;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

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
}
