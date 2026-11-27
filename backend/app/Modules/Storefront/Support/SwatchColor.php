<?php

namespace App\Modules\Storefront\Support;

/**
 * Colour of an attribute option for swatches: the option's own hex when it has one, else a
 * colour recognised from its name (en / ar), else null (the swatch then shows the first
 * letter on a neutral disc). One source for every storefront colour UI (filters, pickers).
 */
final class SwatchColor
{
    /**
     * canonical key => [hex, keywords]; multi-word keywords first so "rose gold" beats "gold".
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const COLORS = [
        'rose-gold' => ['#b76e79', ['rose gold', 'ذهبي وردي', 'روز جولد']],
        'black' => ['#1a1a1a', ['black', 'أسود', 'اسود']],
        'white' => ['#ffffff', ['white', 'ivory', 'أبيض', 'ابيض']],
        'gray' => ['#9a9a9a', ['gray', 'grey', 'رمادي']],
        'silver' => ['#c0c0c0', ['silver', 'فضي', 'فضة']],
        'gold' => ['#d4af37', ['gold', 'golden', 'ذهبي', 'ذهب']],
        'red' => ['#d62828', ['red', 'wine', 'burgundy', 'أحمر', 'احمر']],
        'pink' => ['#f48fb1', ['pink', 'وردي', 'زهري']],
        'orange' => ['#f77f00', ['orange', 'برتقالي']],
        'yellow' => ['#ffd60a', ['yellow', 'أصفر', 'اصفر']],
        'green' => ['#2e9e4f', ['green', 'olive', 'أخضر', 'اخضر']],
        'blue' => ['#1d6fd8', ['blue', 'navy', 'أزرق', 'ازرق', 'كحلي']],
        'purple' => ['#7b2cbf', ['purple', 'violet', 'lilac', 'بنفسجي', 'أرجواني']],
        'brown' => ['#7a4a2b', ['brown', 'coffee', 'tea', 'بني', 'قهوي']],
        'beige' => ['#e1cfae', ['beige', 'khaki', 'cream', 'champagne', 'بيج', 'كريمي']],
        'transparent' => ['#e8f1f5', ['transparent', 'clear', 'شفاف']],
    ];

    /**
     * Hex (#rrggbb) of an option, or null when neither the swatch nor the name tells a colour.
     */
    public static function hex(?string $swatch, string $name): ?string
    {
        $key = self::key($swatch, $name);

        if ($key === null) {
            return null;
        }

        return $key[0] === '#' ? $key : self::COLORS[$key][0];
    }

    /**
     * Group key: the hex itself for a stored swatch, or the canonical colour name; null when unknown.
     */
    public static function key(?string $swatch, string $name): ?string
    {
        if ($swatch !== null && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($swatch)) === 1) {
            $hex = strtolower(trim($swatch));

            return strlen($hex) === 4 ? '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3] : $hex;
        }

        $lower = mb_strtolower($name);

        foreach (self::COLORS as $key => [, $words]) {
            foreach ($words as $word) {
                if (preg_match('/[a-z]/', $word) === 1 ? preg_match('/\b'.preg_quote($word, '/').'\b/u', $lower) === 1 : str_contains($lower, $word)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * True when a check mark / letter on this colour must be dark to stay readable.
     */
    public static function isLight(string $hex): bool
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 170;
    }
}
