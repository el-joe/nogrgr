<?php

namespace App\Modules\Storefront\Support\CustomThemes;

/**
 * Hard limits of custom Twig theme packages and renders (decision #121). Also printed in
 * the vendor documentation (docs/themes/README.md, VEN-036 docs page).
 */
final class CustomThemeLimits
{
    /** Uploaded ZIP size. */
    public const ZIP_MAX_BYTES = 10 * 1024 * 1024;

    /** Sum of all unpacked files. */
    public const UNPACKED_MAX_BYTES = 25 * 1024 * 1024;

    /** Number of files in a package. */
    public const MAX_FILES = 300;

    /** One asset file (css / js / image / font). */
    public const ASSET_MAX_BYTES = 2 * 1024 * 1024;

    /** One Twig template. */
    public const TEMPLATE_MAX_BYTES = 100 * 1024;

    /** All Twig templates together. */
    public const TEMPLATES_MAX_BYTES = 1024 * 1024;

    /** theme.json and each locale file. */
    public const JSON_MAX_BYTES = 200 * 1024;

    /** Largest allowed unpacked / packed ratio of one entry (ZIP bomb guard). */
    public const MAX_COMPRESSION_RATIO = 100;

    /** Longest path inside the package. */
    public const MAX_PATH_LENGTH = 180;

    /** Wall-clock budget of one page render, in milliseconds. */
    public const RENDER_MAX_MS = 1500;

    /** Loop iterations + template / block / macro calls in one render. */
    public const RENDER_MAX_TICKS = 25000;

    /** Nested template / block / macro calls (recursion guard). */
    public const RENDER_MAX_DEPTH = 40;

    /** Largest literal range (`1..n`). */
    public const MAX_RANGE = 500;

    /** Number of vendor settings in theme.json. */
    public const MAX_SETTINGS = 60;

    /** Versions kept per store (oldest non-live ones are pruned beyond this). */
    public const MAX_VERSIONS = 20;

    /**
     * @return array<string, int>
     */
    public static function documented(): array
    {
        return [
            'zip_max_mb' => self::ZIP_MAX_BYTES / 1048576,
            'unpacked_max_mb' => self::UNPACKED_MAX_BYTES / 1048576,
            'max_files' => self::MAX_FILES,
            'asset_max_mb' => self::ASSET_MAX_BYTES / 1048576,
            'template_max_kb' => self::TEMPLATE_MAX_BYTES / 1024,
            'templates_max_kb' => self::TEMPLATES_MAX_BYTES / 1024,
            'json_max_kb' => self::JSON_MAX_BYTES / 1024,
            'render_max_ms' => self::RENDER_MAX_MS,
            'render_max_ticks' => self::RENDER_MAX_TICKS,
            'render_max_depth' => self::RENDER_MAX_DEPTH,
            'max_range' => self::MAX_RANGE,
            'max_settings' => self::MAX_SETTINGS,
            'max_versions' => self::MAX_VERSIONS,
        ];
    }
}
