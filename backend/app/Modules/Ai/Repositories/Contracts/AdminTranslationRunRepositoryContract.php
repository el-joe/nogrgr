<?php

namespace App\Modules\Ai\Repositories\Contracts;

use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Central DB.
 *
 * @extends RepositoryContract<AdminTranslationRun>
 */
interface AdminTranslationRunRepositoryContract extends RepositoryContract
{
    /**
     * The queued / translating run of a kind and locale, if any.
     */
    public function active(AdminTranslationKind $kind, string $locale): ?AdminTranslationRun;

    /**
     * The newest run of a kind (any locale) to show on its page.
     */
    public function latest(AdminTranslationKind $kind, ?string $locale = null): ?AdminTranslationRun;

    /**
     * Add finished / failed items to the run and to one of its sections (row lock).
     */
    public function advance(int $runId, string $section, int $done, int $failed): ?AdminTranslationRun;

    public function addTokens(int $runId, int $prompt, int $completion): void;
}
