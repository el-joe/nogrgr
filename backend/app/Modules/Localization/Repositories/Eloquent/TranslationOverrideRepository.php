<?php

namespace App\Modules\Localization\Repositories\Eloquent;

use App\Modules\Localization\Models\TranslationOverride;
use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<TranslationOverride>
 */
class TranslationOverrideRepository extends BaseRepository implements TranslationOverrideRepositoryContract
{
    protected function model(): string
    {
        return TranslationOverride::class;
    }

    public function mapForLocale(string $locale): array
    {
        $map = [];

        foreach ($this->query()->where('locale', $locale)->toBase()->get(['file', 'key', 'value']) as $row) {
            $map[(string) $row->file][(string) $row->key] = (string) $row->value;
        }

        return $map;
    }

    public function mapForFile(string $file): array
    {
        $map = [];

        foreach ($this->query()->where('file', $file)->toBase()->get(['locale', 'key', 'value']) as $row) {
            $map[(string) $row->locale][(string) $row->key] = (string) $row->value;
        }

        return $map;
    }

    public function files(): array
    {
        /** @var list<string> $files */
        $files = $this->query()->distinct()->orderBy('file')->pluck('file')->map(fn (mixed $file): string => (string) $file)->values()->all();

        return $files;
    }

    public function put(string $file, string $key, string $locale, string $value, ?int $adminId): TranslationOverride
    {
        /** @var TranslationOverride $override */
        $override = $this->query()->updateOrCreate(
            ['file' => $file, 'key' => $key, 'locale' => $locale],
            ['value' => $value, 'updated_by' => $adminId],
        );

        return $override;
    }

    public function forget(string $file, string $key, ?string $locale = null): int
    {
        return $this->query()
            ->where('file', $file)
            ->where('key', $key)
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->delete();
    }
}
