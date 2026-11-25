<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Repositories\Contracts\TenantStaticTextRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TenantStaticText>
 */
class TenantStaticTextRepository extends BaseRepository implements TenantStaticTextRepositoryContract
{
    private const CHUNK = 500;

    protected function model(): string
    {
        return TenantStaticText::class;
    }

    public function valuesFor(string $locale): array
    {
        /** @var array<string, string> $values */
        $values = $this->query()->where('locale', $locale)->pluck('value', 'key')->map(fn (mixed $value): string => (string) $value)->all();

        return $values;
    }

    public function keyedFor(string $locale): Collection
    {
        return $this->query()->where('locale', $locale)->get()->keyBy('key');
    }

    public function findEntry(string $key, string $locale): ?TenantStaticText
    {
        return $this->query()->where('key', $key)->where('locale', $locale)->first();
    }

    public function insertMany(array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $this->query()->insertOrIgnore($chunk);
        }
    }

    public function locales(): array
    {
        /** @var list<string> $locales */
        $locales = $this->query()->distinct()->orderBy('locale')->pluck('locale')->map(fn (mixed $locale): string => (string) $locale)->values()->all();

        return $locales;
    }
}
