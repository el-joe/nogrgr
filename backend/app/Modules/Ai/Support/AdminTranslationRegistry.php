<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Contracts\AdminTranslationSource;
use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\CatalogTranslationSection;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Repositories\Translation\Admin\CentralCatalogSource;
use App\Modules\Ai\Repositories\Translation\Admin\StaticKeySource;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;

/**
 * Sections of an admin AI translation run (ADM-091b catalogue / ADM-092a static keys).
 */
class AdminTranslationRegistry
{
    /**
     * @return array<string, AdminTranslationSource> section => source
     */
    public function sources(AdminTranslationRun $run): array
    {
        if ($run->kind === AdminTranslationKind::StaticKeys) {
            $source = $this->staticKeys($run->scopeFile());

            return [$source->section() => $source];
        }

        $sources = [];

        foreach (CatalogTranslationSection::cases() as $section) {
            $sources[$section->value] = new CentralCatalogSource($section);
        }

        return $sources;
    }

    public function source(AdminTranslationRun $run, string $section): ?AdminTranslationSource
    {
        return $this->sources($run)[$section] ?? null;
    }

    public function staticKeys(?string $file): StaticKeySource
    {
        return new StaticKeySource(app(TranslationEditorService::class), app(TranslationFileRegistry::class), $file);
    }
}
