<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TenantLanguage>
 */
class TenantLanguageRepository extends BaseRepository implements TenantLanguageRepositoryContract
{
    protected function model(): string
    {
        return TenantLanguage::class;
    }

    public function findByCode(string $code): ?TenantLanguage
    {
        return $this->query()->where('language_code', strtolower($code))->first();
    }

    public function ordered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function codes(): array
    {
        /** @var list<string> $codes */
        $codes = $this->query()->pluck('language_code')->map(fn (mixed $code): string => (string) $code)->values()->all();

        return $codes;
    }

    public function clearDefault(): void
    {
        $this->query()->where('is_default', true)->update(['is_default' => false]);
    }

    public function nextSortOrder(): int
    {
        return (int) $this->query()->max('sort_order') + 1;
    }
}
