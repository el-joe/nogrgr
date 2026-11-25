<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\TranslationOverride;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Central DB.
 *
 * @extends RepositoryContract<TranslationOverride>
 */
interface TranslationOverrideRepositoryContract extends RepositoryContract
{
    /**
     * Every override of a locale: file => [key => value].
     *
     * @return array<string, array<string, string>>
     */
    public function mapForLocale(string $locale): array;

    /**
     * Overrides of one file: locale => [key => value].
     *
     * @return array<string, array<string, string>>
     */
    public function mapForFile(string $file): array;

    /**
     * Distinct file ids that have overrides (files created in the editor included).
     *
     * @return list<string>
     */
    public function files(): array;

    public function put(string $file, string $key, string $locale, string $value, ?int $adminId): TranslationOverride;

    public function forget(string $file, string $key, ?string $locale = null): int;
}
