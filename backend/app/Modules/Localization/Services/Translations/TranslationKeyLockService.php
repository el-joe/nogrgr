<?php

namespace App\Modules\Localization\Services\Translations;

use App\Modules\Localization\Repositories\Contracts\TranslationKeyLockRepositoryContract;
use Throwable;

/**
 * Translation key locks (ADM-093). A locked key is frozen: AI translation runs (P10) skip
 * it, vendors cannot edit a locked storefront text (stores show the base value) and the
 * admin editor refuses to save it until it is unlocked. The lock map is memoised per
 * request (one central query).
 */
class TranslationKeyLockService
{
    /**
     * @var array<string, array<string, true>>|null
     */
    private ?array $map = null;

    public function __construct(private readonly TranslationKeyLockRepositoryContract $locks) {}

    public function isLocked(string $file, string $key): bool
    {
        return isset($this->map()[$file][$key]);
    }

    /**
     * @return array<string, true>
     */
    public function lockedKeys(string $file): array
    {
        return $this->map()[$file] ?? [];
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->map()));
    }

    public function lock(string $file, string $key, ?int $adminId = null): void
    {
        $this->locks->lock($file, $key, $adminId);
        $this->map = null;
    }

    public function unlock(string $file, string $key): void
    {
        $this->locks->unlock($file, $key);
        $this->map = null;
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        try {
            return $this->map = $this->locks->map();
        } catch (Throwable) {
            return $this->map = [];
        }
    }
}
