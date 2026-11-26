<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\ContentTranslationLock;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<ContentTranslationLock>
 */
class ContentTranslationLockRepository extends BaseRepository implements ContentTranslationLockRepositoryContract
{
    protected function model(): string
    {
        return ContentTranslationLock::class;
    }

    public function lock(string $type, int $id, string $field, string $locale): void
    {
        $this->query()->firstOrCreate(['content_type' => $type, 'content_id' => $id, 'field' => $field, 'locale' => $locale]);
    }

    public function unlock(string $type, int $id, string $field, string $locale): void
    {
        $this->query()->where(['content_type' => $type, 'content_id' => $id, 'field' => $field, 'locale' => $locale])->delete();
    }

    public function lockedKeys(string $type, string $locale): array
    {
        $keys = $this->query()->where('content_type', $type)->where('locale', $locale)->get(['content_id', 'field'])
            ->map(static fn (ContentTranslationLock $lock): string => $lock->content_id.'|'.$lock->field)
            ->all();

        return array_fill_keys($keys, true);
    }
}
