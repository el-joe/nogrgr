<?php

namespace App\Modules\Ai\Repositories\Translation\Admin;

/**
 * Source-locale rule of the admin runs: a text is translated from English, else Arabic
 * (never from the target locale itself).
 */
final class MissingTexts
{
    /**
     * @return list<string>
     */
    public static function sourceLocales(string $target): array
    {
        return array_values(array_filter(['en', 'ar'], static fn (string $code): bool => $code !== $target));
    }

    /**
     * [source locale, text] of a field, or null when it has no source text.
     *
     * @param  array<string, mixed>  $values  locale => text
     * @return array{0: string, 1: string}|null
     */
    public static function source(array $values, string $target): ?array
    {
        foreach (self::sourceLocales($target) as $code) {
            $text = $values[$code] ?? null;

            if (is_string($text) && trim($text) !== '') {
                return [$code, $text];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function isMissing(array $values, string $target): bool
    {
        $text = $values[$target] ?? null;

        return ! is_string($text) || trim($text) === '';
    }
}
