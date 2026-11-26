<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * The one woff2 worth preloading per surface and direction (decision #89): the regular
 * weight of the body family from the Figma font tokens. Latin pages use Outfit; Arabic pages
 * use the surface's Arabic family (IBM Plex Sans Arabic in the vendor panel, Noto Sans
 * Arabic everywhere else). Files are self-hosted from npm @fontsource and resolved through
 * the Vite manifest; when the asset is not built (tests, missing manifest) nothing is
 * preloaded and the @font-face swap still applies.
 */
final class FontPreload
{
    public const LATIN = 'node_modules/@fontsource/outfit/files/outfit-latin-400-normal.woff2';

    public const ARABIC = 'node_modules/@fontsource/noto-sans-arabic/files/noto-sans-arabic-arabic-400-normal.woff2';

    public const ARABIC_VENDOR = 'node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2';

    public static function path(string $surface, bool $rtl): string
    {
        if (! $rtl) {
            return self::LATIN;
        }

        return $surface === 'vendor' ? self::ARABIC_VENDOR : self::ARABIC;
    }

    public static function url(string $surface, bool $rtl): ?string
    {
        try {
            $url = (string) Vite::asset(self::path($surface, $rtl));
        } catch (Throwable) {
            return null;
        }

        return $url !== '' ? $url : null;
    }
}
