<?php

namespace App\Modules\Storefront\Support\Listing;

use App\Modules\Storefront\Support\SwatchColor;

/**
 * Order and shape of the Elora filter groups (Figma 1:17172 sidebar · m 1:22799 sheet):
 * Brand, Size, Promotional items, Measurements, Color, Price, Gender, Product rating,
 * Pattern, Fabric Type, then any other variation and Availability. Variation groups only
 * exist when the products in scope have them.
 */
final class FilterGroups
{
    /**
     * Rows shown before "Show more" (Figma "Popular brands" lists eight).
     */
    public const VISIBLE = 8;

    private const NAMED = [
        'brand' => '/brand|ماركة|الماركة|علامة/iu',
        'size' => '/size|مقاس|المقاس|حجم/iu',
        'measurements' => '/measure|قياس/iu',
        'color' => '/colou?r|لون|اللون/iu',
        'gender' => '/gender|جنس|النوع/iu',
        'pattern' => '/pattern|نقش|النقشة/iu',
        'fabric' => '/fabric|material|خامة|قماش|الخامة/iu',
    ];

    private const ORDER = ['brand', 'size', 'promo', 'measurements', 'color', 'price', 'gender', 'rating', 'pattern', 'fabric', 'other', 'availability'];

    /**
     * @param  list<array{id: int, name: string, values: list<array{value: string, count: int}>}>  $attributes
     * @return list<array{type: string, key: string, attribute: array<string, mixed>|null}>
     */
    public static function order(array $attributes): array
    {
        $groups = [];

        foreach (['promo', 'price', 'rating', 'availability'] as $type) {
            $groups[] = ['type' => $type, 'key' => $type, 'attribute' => null];
        }

        foreach ($attributes as $attribute) {
            $key = 'other';

            foreach (self::NAMED as $name => $pattern) {
                if (preg_match($pattern, (string) $attribute['name']) === 1) {
                    $key = $name;
                    break;
                }
            }

            $groups[] = ['type' => 'options', 'key' => $key, 'attribute' => $attribute];
        }

        $position = array_flip(self::ORDER);
        usort($groups, static fn (array $a, array $b): int => $position[$a['key']] <=> $position[$b['key']]);

        return $groups;
    }

    /**
     * Rows of one variation group: the most used values first (the "popular" ones), every
     * selected value kept visible, the rest behind "Show more".
     *
     * @param  list<array{value: string, count: int}>  $values  most products first
     * @param  list<string>  $selected
     * @return list<array{value: string, count: int, checked: bool, more: bool}>
     */
    public static function rows(array $values, array $selected): array
    {
        $rows = [];

        foreach (array_values($values) as $index => $option) {
            $value = (string) $option['value'];
            $checked = in_array($value, $selected, true);
            $rows[] = ['value' => $value, 'count' => (int) $option['count'], 'checked' => $checked, 'more' => $index >= self::VISIBLE && ! $checked];
        }

        return $rows;
    }

    /**
     * Colour dot of a value of the colour group (null when the value names no colour).
     */
    public static function dot(string $key, string $value): ?string
    {
        return $key === 'color' ? SwatchColor::hex(null, $value) : null;
    }
}
