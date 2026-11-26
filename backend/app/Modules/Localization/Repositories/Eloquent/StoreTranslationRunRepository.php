<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
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
}
