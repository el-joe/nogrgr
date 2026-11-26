<?php

namespace App\Modules\Ai\Services\StoreTranslation;

use App\Modules\Localization\Enums\TranslationRunTrigger;
use App\Modules\Localization\Models\StoreTranslationRun;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\StoreTranslationRunRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Localization\Services\StoreLanguageService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Weekly delta auto-translation (decision #44 "source-hash delta … included for the plan
 * term"): for every active store × bought language whose latest run completed and that is
 * still switched on, a delta run is queued. The run machinery skips every item whose
 * source hash did not change, so only new / changed content reaches the provider.
 */
class DeltaTranslationService extends BaseService
{
    public function __construct(
        private readonly StoreTranslationRunRepositoryContract $runs,
        private readonly TenantRepositoryContract $tenants,
        private readonly StoreLanguageService $languages,
    ) {}

    /**
     * @return list<StoreTranslationRun> the queued delta runs
     */
    public function queueDue(): array
    {
        $queued = [];

        foreach ($this->runs->latestCompletedOfActiveStores() as $latest) {
            $tenant = $this->tenants->find($latest->tenant_id);

            if (! $tenant instanceof Tenant || ! $this->enabled($tenant, $latest->language_code)) {
                continue;
            }

            $queued[] = $this->languages->queueRun((int) $tenant->id, $latest->language_id, $latest->language_code, null, TranslationRunTrigger::Delta);
        }

        return $queued;
    }

    private function enabled(Tenant $tenant, string $code): bool
    {
        return (bool) $tenant->run(static function () use ($code): bool {
            $language = app(TenantLanguageRepositoryContract::class)->findByCode($code);

            return $language instanceof TenantLanguage && $language->is_enabled;
        });
    }
}
