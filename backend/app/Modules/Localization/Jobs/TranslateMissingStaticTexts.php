<?php

namespace App\Modules\Localization\Jobs;

use App\Modules\Localization\Services\StoreTranslationService;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Vendor "Translate missing with AI" (VEN-075): fills the store's empty static texts of
 * one language with the custom translation provider (decision #44 a). Locked and
 * manually edited rows are never touched. Tokens are logged to the AI usage log (feature
 * static_key_translation, the store as tenant).
 */
class TranslateMissingStaticTexts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $tenantId, public readonly string $locale) {}

    public function uniqueId(): string
    {
        return $this->tenantId.'|'.$this->locale;
    }

    public function handle(TenantRepositoryContract $tenants, StoreTranslationService $translations): void
    {
        $tenant = $tenants->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(fn () => $translations->fillMissingStaticTexts($this->locale));
    }
}
