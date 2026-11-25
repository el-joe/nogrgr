<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\DTOs\LanguageData;
use App\Modules\Localization\Enums\TenantLanguageSource;
use App\Modules\Localization\Exceptions\LockedRecordException;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central language list (admin) and the store side of decision #45: ar + en are free
 * and always on; every other active, priced language can be bought (Billing add-on),
 * and the paid AI translation run (P10) switches it on with activateForTenant().
 */
class LanguageService
{
    public function __construct(
        private readonly LanguageRepositoryContract $languages,
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
    ) {}

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        return $this->languages->active();
    }

    public function activate(string $code): Language
    {
        $language = $this->findOrFail($code);

        return $this->languages->update($language, ['is_active' => true]);
    }

    public function deactivate(string $code): Language
    {
        $language = $this->findOrFail($code);

        if ($language->isCore()) {
            throw LockedRecordException::coreLanguage();
        }

        if ($language->is_default) {
            throw new DomainException(__('localization::admin.languages.default_locked'));
        }

        return $this->languages->update($language, ['is_active' => false]);
    }

    public function setDefault(string $code): Language
    {
        $language = $this->findOrFail($code);

        if (! $language->is_active) {
            throw new DomainException(__('localization::admin.languages.default_requires_active'));
        }

        return DB::transaction(function () use ($language): Language {
            $this->languages->query()
                ->where('is_default', true)
                ->whereKeyNot($language->getKey())
                ->update(['is_default' => false]);

            return $this->languages->update($language, ['is_default' => true]);
        });
    }

    public function default(): ?Language
    {
        return $this->languages->query()->where('is_default', true)->first()
            ?? $this->languages->findByCode(Language::FALLBACK);
    }

    /**
     * Tab counters of the admin list.
     *
     * @return array{active: int, all: int}
     */
    public function counts(): array
    {
        return [
            'active' => $this->languages->query()->where('is_active', true)->count(),
            'all' => $this->languages->query()->count(),
        ];
    }

    public function create(LanguageData $data): Language
    {
        return $this->languages->create($this->attributes($data) + ['code' => strtolower($data->code)]);
    }

    /**
     * The code is the language's identity and never changes. ar / en stay active and free.
     */
    public function update(Language $language, LanguageData $data): Language
    {
        if ($language->isCore() && ! $data->isActive) {
            throw LockedRecordException::coreLanguage();
        }

        if ($language->is_default && ! $data->isActive) {
            throw new DomainException(__('localization::admin.languages.default_locked'));
        }

        return $this->languages->update($language, $this->attributes($data));
    }

    public function setActive(Language $language, bool $active): Language
    {
        return $active ? $this->activate($language->code) : $this->deactivate($language->code);
    }

    public function delete(Language $language): void
    {
        if ($language->isCore()) {
            throw LockedRecordException::coreLanguage();
        }

        if ($language->is_default) {
            throw new DomainException(__('localization::admin.languages.default_locked'));
        }

        $this->languages->delete($language);
    }

    /**
     * Languages the store can buy: active, priced, not ar / en, not already on the store,
     * and offered in at least one of the store's selling countries (all when either list
     * is empty).
     *
     * @return Collection<int, Language>
     */
    public function availableToPurchase(Tenant $tenant): Collection
    {
        /** @var list<string> $owned */
        $owned = $tenant->run(fn (): array => $this->tenantLanguages->codes());
        $selling = array_map('intval', array_values(array_filter((array) $tenant->getAttribute('selling_country_ids'), 'is_numeric')));

        return $this->languages->active()
            ->filter(static fn (Language $language): bool => $language->isPurchasable()
                && ! in_array($language->code, $owned, true)
                && ($selling === [] || $language->country_ids === null || $language->country_ids === []
                    || array_intersect($selling, array_map('intval', $language->country_ids)) !== []))
            ->values()
            ->toBase();
    }

    /**
     * Switch a bought language on for the store once its paid AI translation run has
     * finished (called by the P10 run). Idempotent.
     */
    public function activateForTenant(Tenant $tenant, string $code): TenantLanguage
    {
        $language = $this->findOrFail($code);

        if ($language->isCore()) {
            throw LockedRecordException::coreLanguage();
        }

        return $tenant->run(function () use ($language): TenantLanguage {
            $existing = $this->tenantLanguages->findByCode($language->code);

            if ($existing !== null) {
                return $this->tenantLanguages->update($existing, ['is_enabled' => true, 'source' => TenantLanguageSource::Purchased]);
            }

            return $this->tenantLanguages->create([
                'language_code' => $language->code,
                'is_enabled' => true,
                'is_default' => false,
                'sort_order' => $this->tenantLanguages->nextSortOrder(),
                'source' => TenantLanguageSource::Purchased,
                'enabled_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(LanguageData $data): array
    {
        $countries = $data->countryIds === null || $data->countryIds === []
            ? null
            : array_values(array_unique(array_map('intval', $data->countryIds)));

        return [
            'name' => array_filter($data->name, static fn (?string $value): bool => $value !== null && $value !== ''),
            'native_name' => $data->nativeName,
            'direction' => $data->direction,
            'flag' => $data->flag !== null && $data->flag !== '' ? strtoupper($data->flag) : null,
            'is_active' => $data->isActive,
            'price_minor' => in_array(strtolower($data->code), Language::CORE, true) ? 0 : Money::of($data->price ?? 0)->minor,
            'country_ids' => $countries,
            'sort_order' => $data->sortOrder,
        ];
    }

    private function findOrFail(string $code): Language
    {
        return $this->languages->findByCode($code)
            ?? throw (new ModelNotFoundException)->setModel(Language::class, [$code]);
    }
}
