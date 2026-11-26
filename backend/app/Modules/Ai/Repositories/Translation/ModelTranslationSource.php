<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A store-owned translatable model (tenant DB) as a translation section: every record
 * of the query, every aiTranslatableFields() field. The source locale of a record is
 * English when it has English text, else Arabic (the two core languages).
 */
class ModelTranslationSource implements StoreTranslationSource
{
    /**
     * @param  class-string<Model&AiTranslatable>  $model
     * @param  (Closure(): Builder<Model>)|null  $query
     */
    public function __construct(
        private readonly StoreTranslationSection $section,
        private readonly string $model,
        private readonly ContentTranslationLockRepositoryContract $locks,
        private readonly ?Closure $query = null,
    ) {}

    public function section(): StoreTranslationSection
    {
        return $this->section;
    }

    public function keys(string $locale): array
    {
        return $this->query()->orderBy('id')->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
    }

    public function items(array $keys, string $locale, array $retranslate = []): array
    {
        $locked = $this->locks->lockedKeys($this->section->lockType(), $locale);
        $items = [];

        /** @var Model&AiTranslatable $record */
        foreach ($this->query()->whereKey(array_map('intval', $keys))->get() as $record) {
            $key = (string) $record->getKey();
            $items[$key] = SourceTexts::item($key, $record->aiTranslatableFields(), $locale, $retranslate !== [] && isset($retranslate[$key]),
                static fn (string $field, string $code): string => (string) $record->getTranslation($field, $code, false),
                static fn (string $field): bool => isset($locked[$key.'|'.$field]),
            );
        }

        return $items;
    }

    public function write(string $key, string $locale, array $translations): void
    {
        /** @var (Model&AiTranslatable)|null $record */
        $record = $this->query()->whereKey((int) $key)->first();

        if ($record === null || $translations === []) {
            return;
        }

        foreach ($translations as $field => $text) {
            $record->setTranslation($field, $locale, $text);
        }

        $record->save();
    }

    public function models(): array
    {
        return [$this->model];
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        return $this->query !== null ? ($this->query)() : $this->model::query();
    }
}
