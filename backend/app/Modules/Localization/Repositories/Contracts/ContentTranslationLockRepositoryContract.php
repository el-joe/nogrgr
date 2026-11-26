<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\ContentTranslationLock;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<ContentTranslationLock>
 */
interface ContentTranslationLockRepositoryContract extends RepositoryContract
{
    public function lock(string $type, int $id, string $field, string $locale): void;

    public function unlock(string $type, int $id, string $field, string $locale): void;

    /**
     * "id|field" keys locked for one content type in one locale.
     *
     * @return array<string, true>
     */
    public function lockedKeys(string $type, string $locale): array;
}
