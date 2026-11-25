<?php

namespace App\Modules\Localization\Repositories\Contracts;

use App\Modules\Localization\Models\TranslationKeyLock;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Central DB.
 *
 * @extends RepositoryContract<TranslationKeyLock>
 */
interface TranslationKeyLockRepositoryContract extends RepositoryContract
{
    /**
     * Locked keys of a file: key => true.
     *
     * @return array<string, true>
     */
    public function keysOf(string $file): array;

    /**
     * @return array<string, array<string, true>>
     */
    public function map(): array;

    public function isLocked(string $file, string $key): bool;

    public function lock(string $file, string $key, ?int $adminId): TranslationKeyLock;

    public function unlock(string $file, string $key): bool;
}
