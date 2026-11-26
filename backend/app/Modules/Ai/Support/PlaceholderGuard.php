<?php

namespace App\Modules\Ai\Support;

/**
 * Decision #44 "placeholder / HTML / markdown / URL preservation check": a translation
 * is accepted only when it keeps every placeholder ({{ var }}, {var}, :name, %s), URL,
 * e-mail address and HTML tag of the source, the same number of times.
 */
final class PlaceholderGuard
{
    private const TOKENS = '/\{\{\s*[\w.\-]+\s*\}\}|\{[\w.\-]+\}|(?<![\w:]):[a-z_]{2,}\b|%(?:\d+\$)?[sd]|https?:\/\/[^\s"\'<>]+|[\w.+\-]+@[\w\-]+\.[\w.\-]+/iu';

    private const TAGS = '/<\/?([a-z][a-z0-9]*)\b/i';

    public static function preserved(string $source, string $translation): bool
    {
        return self::tokens($source) === self::tokens($translation) && self::tags($source) === self::tags($translation);
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        preg_match_all(self::TOKENS, $text, $matches);
        $tokens = array_map(static fn (string $token): string => (string) preg_replace('/\s+/', '', rtrim($token, '.,;:!?)')), $matches[0]);
        sort($tokens);

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function tags(string $text): array
    {
        preg_match_all(self::TAGS, $text, $matches);
        $tags = array_map('strtolower', $matches[0]);
        sort($tags);

        return $tags;
    }
}
