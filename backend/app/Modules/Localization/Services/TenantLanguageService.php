<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Enums\TenantLanguageSource;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Languages of the current store (tenant DB — tenancy must be initialized).
 */
class TenantLanguageService
{
    public function __construct(private readonly TenantLanguageRepositoryContract $tenantLanguages) {}

    /**
     * Switch on Arabic and English for the current store (called by tenant provisioning).
     * Idempotent: existing rows are re-enabled; English becomes the default only when the
     * store has no default yet.
     *
     * @return Collection<int, TenantLanguage>
     */
    public function enableCoreLanguages(): Collection
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenancy must be initialized to enable store languages.');
        }

        DB::connection((new TenantLanguage)->getConnectionName())->transaction(function (): void {
            $hasDefault = $this->tenantLanguages->query()->where('is_default', true)->exists();

            foreach (Language::CORE as $position => $code) {
                $existing = $this->tenantLanguages->findByCode($code);
                $attributes = ['is_enabled' => true, 'source' => TenantLanguageSource::Default];

                if ($existing !== null) {
                    $this->tenantLanguages->update($existing, $attributes);

                    continue;
                }

                $this->tenantLanguages->create($attributes + [
                    'language_code' => $code,
                    'is_default' => ! $hasDefault && $code === Language::FALLBACK,
                    'sort_order' => $position + 1,
                    'enabled_at' => now(),
                ]);
            }
        });

        return $this->tenantLanguages->ordered();
    }
}
