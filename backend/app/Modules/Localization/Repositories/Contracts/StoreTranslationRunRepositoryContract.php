<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\StoreTranslationRun;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

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
}
