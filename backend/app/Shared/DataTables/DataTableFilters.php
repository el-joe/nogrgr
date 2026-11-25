<?php

namespace App\Shared\DataTables;

use Illuminate\Http\Request;

/**
 * Safe extraction of list filters from a DataTables request.
 *
 * DataTables always sends its own structured parameters (`search[value]`,
 * `search[regex]`, `columns[…]`, `order[…]`), so a filter named like one of them
 * arrives as an array. This keeps only the allowed keys with scalar values (or flat
 * lists of scalars for multi-selects) and reads the global search box from
 * `search[value]` — the filters bar feeds its search field into it.
 */
final class DataTableFilters
{
    /**
     * @param  list<string>  $keys
     * @return array<string, string|list<string>>
     */
    public static function fromRequest(Request $request, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $key === 'search' ? $request->input('search.value', $request->input('search')) : $request->input($key);
            $clean = self::clean($value);

            if ($clean !== null) {
                $values[$key] = $clean;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|list<string>>
     */
    public static function sanitize(array $filters): array
    {
        $values = [];

        foreach ($filters as $key => $value) {
            $value = $key === 'search' && is_array($value) ? ($value['value'] ?? null) : $value;
            $clean = self::clean($value);

            if ($clean !== null) {
                $values[(string) $key] = $clean;
            }
        }

        return $values;
    }

    /**
     * @return string|list<string>|null
     */
    private static function clean(mixed $value): string|array|null
    {
        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        if (is_array($value) && array_is_list($value)) {
            $list = array_values(array_filter(array_map(static fn ($item): ?string => is_scalar($item) && trim((string) $item) !== '' ? trim((string) $item) : null, $value)));

            return $list === [] ? null : $list;
        }

        return null;
    }
}
