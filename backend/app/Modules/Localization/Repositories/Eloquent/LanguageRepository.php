<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Language>
 */
class LanguageRepository extends BaseRepository implements LanguageRepositoryContract
{
    protected function model(): string
    {
        return Language::class;
    }

    public function findByCode(string $code): ?Language
    {
        return $this->query()->where('code', strtolower($code))->first();
    }

    public function findActiveByCode(string $code): ?Language
    {
        return $this->query()->active()->where('code', strtolower($code))->first();
    }

    public function active(): Collection
    {
        return $this->query()->active()->orderBy('sort_order')->orderBy('code')->get();
    }

    public function upsertMany(array $rows): int
    {
        return $this->query()->upsert($rows, ['code'], ['name', 'native_name', 'direction', 'flag', 'updated_at']);
    }
}
