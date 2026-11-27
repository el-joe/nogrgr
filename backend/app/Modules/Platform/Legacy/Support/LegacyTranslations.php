<?php

namespace App\Modules\Platform\Legacy\Support;

use Illuminate\Database\Connection;

/**
 * The old polymorphic `translations` table (language_id, translatable_type/id, field,
 * value) folded into the new `{locale: text}` JSON columns. Loaded per chunk of ids
 * (one query per chunk, never per row).
 */
class LegacyTranslations
{
    /** @var array<int, string> old language id => locale code */
    private array $locales;

    public function __construct(private readonly Connection $connection)
    {
        $this->locales = [];

        if ($connection->getSchemaBuilder()->hasTable('languages')) {
            foreach ($connection->table('languages')->get(['id', 'code']) as $language) {
                $this->locales[(int) $language->id] = strtolower((string) $language->code);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, array<string, string>>> id => field => locale => value
     */
    public function load(string $type, array $ids): array
    {
        if ($ids === [] || ! $this->connection->getSchemaBuilder()->hasTable('translations')) {
            return [];
        }

        $out = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = $this->connection->table('translations')
                ->where('translatable_type', $type)
                ->whereIn('translatable_id', $chunk)
                ->get(['translatable_id', 'language_id', 'field', 'value']);

            foreach ($rows as $row) {
                $locale = $this->locales[(int) $row->language_id] ?? null;

                if ($locale === null || $row->value === null || trim((string) $row->value) === '') {
                    continue;
                }

                $out[(int) $row->translatable_id][(string) $row->field][$locale] = (string) $row->value;
            }
        }

        return $out;
    }

    /**
     * One translatable field, with a plain-column fallback stored under English.
     *
     * @param  array<int, array<string, array<string, string>>>  $loaded
     * @return array<string, string>|null
     */
    public static function field(array $loaded, int $id, string $field, ?string $fallback = null): ?array
    {
        $value = $loaded[$id][$field] ?? [];

        if ($value === [] && $fallback !== null && trim($fallback) !== '') {
            $value = ['en' => $fallback];
        }

        return $value === [] ? null : $value;
    }
}
