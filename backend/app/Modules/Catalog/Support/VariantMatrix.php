<?php

namespace App\Modules\Catalog\Support;

/**
 * Variant combinations: the cartesian product of the chosen option ids of each attribute,
 * and the stable key of one combination (sorted option ids joined by "-").
 */
final class VariantMatrix
{
    /**
     * @param  array<int, list<int>>  $groups  attribute id => option ids
     * @return list<list<int>>
     */
    public static function combinations(array $groups): array
    {
        $groups = array_values(array_filter($groups, static fn (array $options): bool => $options !== []));

        if ($groups === []) {
            return [];
        }

        $result = [[]];

        foreach ($groups as $options) {
            $next = [];

            foreach ($result as $combination) {
                foreach (array_values(array_unique($options)) as $option) {
                    $next[] = [...$combination, (int) $option];
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * @param  list<int|string>  $optionIds
     */
    public static function key(array $optionIds): string
    {
        $ids = array_map('intval', $optionIds);
        sort($ids);

        return implode('-', $ids);
    }
}
