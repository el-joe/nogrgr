<?php

namespace App\Modules\Ai\Repositories\Translation\Admin;

use App\Modules\Ai\Contracts\AdminTranslationSource;
use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\CatalogTranslationSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Central catalogue models of one section (ADM-091b): item key "<prefix>:<id>"; only
 * fields empty in the target locale that have an English (else Arabic) source are
 * translated, filled fields are never overwritten.
 */
class CentralCatalogSource implements AdminTranslationSource
{
    private const SCAN_CHUNK = 500;

    public function __construct(private readonly CatalogTranslationSection $section) {}

    public function section(): string
    {
        return $this->section->value;
    }

    public function keys(string $locale): array
    {
        $keys = [];

        foreach ($this->section->models() as $prefix => $class) {
            /** @var Model&AiTranslatable $model */
            $model = new $class;
            $fields = $model->aiTranslatableFields();

            $this->query($class)->select(array_merge([$model->getKeyName()], $fields))
                ->chunkById(self::SCAN_CHUNK, function ($records) use (&$keys, $prefix, $fields, $locale): void {
                    foreach ($records as $record) {
                        if ($this->missingFields($record, $fields, $locale) !== []) {
                            $keys[] = $prefix.':'.$record->getKey();
                        }
                    }
                });
        }

        return $keys;
    }

    public function items(array $keys, string $locale): array
    {
        $items = [];

        foreach ($this->records($keys) as $key => $record) {
            $fields = $this->missingFields($record, $record->aiTranslatableFields(), $locale);

            if ($fields === []) {
                continue;
            }

            $sources = [];

            foreach ($record->aiTranslatableFields() as $field) {
                $source = MissingTexts::source($record->getTranslations($field), $locale);

                if ($source !== null) {
                    $sources[$field] = $source;
                }
            }

            $items[$key] = TranslationItemData::make($key, $sources, $fields);
        }

        return $items;
    }

    public function write(string $locale, array $translations): void
    {
        foreach ($this->records(array_keys($translations)) as $key => $record) {
            $changed = false;

            foreach ($translations[$key] ?? [] as $field => $text) {
                if (! in_array($field, $record->aiTranslatableFields(), true) || trim($text) === ''
                    || ! MissingTexts::isMissing($record->getTranslations($field), $locale)) {
                    continue;
                }

                $record->setTranslation($field, $locale, $text);
                $changed = true;
            }

            if ($changed) {
                $record->save();
            }
        }
    }

    /**
     * @param  Model&AiTranslatable  $record
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function missingFields(Model $record, array $fields, string $locale): array
    {
        $missing = [];

        foreach ($fields as $field) {
            $values = $record->getTranslations($field);

            if (MissingTexts::isMissing($values, $locale) && MissingTexts::source($values, $locale) !== null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, Model&AiTranslatable>
     */
    private function records(array $keys): array
    {
        $models = $this->section->models();
        $ids = [];

        foreach ($keys as $key) {
            [$prefix, $id] = array_pad(explode(':', (string) $key, 2), 2, '');

            if (isset($models[$prefix]) && ctype_digit($id)) {
                $ids[$prefix][] = (int) $id;
            }
        }

        $records = [];

        foreach ($ids as $prefix => $list) {
            foreach ($this->query($models[$prefix])->whereKey($list)->get() as $record) {
                $records[$prefix.':'.$record->getKey()] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  class-string<Model&AiTranslatable>  $class
     * @return Builder<Model>
     */
    private function query(string $class): Builder
    {
        return $class::query();
    }
}
