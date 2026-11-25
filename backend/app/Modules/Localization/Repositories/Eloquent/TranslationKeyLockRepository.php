<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\TranslationKeyLock;
use App\Modules\Localization\Repositories\Contracts\TranslationKeyLockRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<TranslationKeyLock>
 */
class TranslationKeyLockRepository extends BaseRepository implements TranslationKeyLockRepositoryContract
{
    protected function model(): string
    {
        return TranslationKeyLock::class;
    }

    public function keysOf(string $file): array
    {
        return $this->map()[$file] ?? [];
    }

    public function map(): array
    {
        $map = [];

        foreach ($this->query()->toBase()->get(['file', 'key']) as $row) {
            $map[(string) $row->file][(string) $row->key] = true;
        }

        return $map;
    }

    public function isLocked(string $file, string $key): bool
    {
        return $this->query()->where('file', $file)->where('key', $key)->exists();
    }

    public function lock(string $file, string $key, ?int $adminId): TranslationKeyLock
    {
        /** @var TranslationKeyLock $lock */
        $lock = $this->query()->firstOrCreate(['file' => $file, 'key' => $key], ['locked_by' => $adminId]);

        return $lock;
    }

    public function unlock(string $file, string $key): bool
    {
        return $this->query()->where('file', $file)->where('key', $key)->delete() > 0;
    }
}
