<?php

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LanguageService
{
    public function __construct(private readonly LanguageRepositoryContract $languages) {}

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
            throw new DomainException(__('Core languages (Arabic and English) cannot be deactivated.'));
        }

        if ($language->is_default) {
            throw new DomainException(__('The default language cannot be deactivated.'));
        }

        return $this->languages->update($language, ['is_active' => false]);
    }

    public function setDefault(string $code): Language
    {
        $language = $this->findOrFail($code);

        if (! $language->is_active) {
            throw new DomainException(__('Only an active language can be set as default.'));
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

    private function findOrFail(string $code): Language
    {
        return $this->languages->findByCode($code)
            ?? throw (new ModelNotFoundException)->setModel(Language::class, [$code]);
    }
}
