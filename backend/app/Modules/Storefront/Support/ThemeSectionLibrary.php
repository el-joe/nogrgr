<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Storefront\Enums\ThemeFamily;

/**
 * Home section library of each catalogue theme (ADM-087, decision #120): the sections the
 * theme's home view can render, in the Figma order. Elora / Souqify come from their
 * variant config (#79 / #83), Ecommet from its fixed home (1:1002). Every section gets a
 * stable key (type, or type.data for repeated rails, suffixed -2… when repeated again),
 * which the admin home-section defaults and the page builder store.
 */
final class ThemeSectionLibrary
{
    /**
     * Ecommet home sections (themes/ecommet/pages/home.blade.php).
     *
     * @var list<array<string, string>>
     */
    private const ECOMMET = [
        ['type' => 'category-strip'],
        ['type' => 'hero'],
        ['type' => 'why-choose'],
        ['type' => 'deals'],
        ['type' => 'interests'],
        ['type' => 'flash-band'],
        ['type' => 'best-grid'],
        ['type' => 'mobile-tabs'],
    ];

    /**
     * @return list<array<string, mixed>> each section definition + `key`
     */
    public static function sections(ThemeFamily $family, int $variant): array
    {
        $definitions = match ($family) {
            ThemeFamily::Elora => EloraVariants::sections($variant),
            ThemeFamily::Souqify => SouqifyVariants::sections($variant),
            ThemeFamily::Ecommet => self::ECOMMET,
        };

        $seen = [];
        $sections = [];

        foreach ($definitions as $definition) {
            $base = (string) $definition['type'].(isset($definition['data']) && is_string($definition['data']) ? '.'.$definition['data'] : '');
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $key = $seen[$base] > 1 ? $base.'-'.$seen[$base] : $base;
            $sections[] = $definition + ['key' => $key];
        }

        return $sections;
    }

    /**
     * Section keys in library order.
     *
     * @return list<string>
     */
    public static function keys(ThemeFamily $family, int $variant): array
    {
        return array_column(self::sections($family, $variant), 'key');
    }

    /**
     * Translated label of a section definition ("Product rail · Trending").
     *
     * @param  array<string, mixed>  $section
     */
    public static function label(array $section): string
    {
        $type = (string) $section['type'];
        $label = (string) __('content::admin_themes.sections.types.'.$type);

        if (isset($section['data']) && is_string($section['data'])) {
            $label .= ' · '.__('content::admin_themes.sections.data.'.$section['data']);
        }

        return $label;
    }

    /**
     * Short design note (the style the section uses in this theme), when it has one.
     *
     * @param  array<string, mixed>  $section
     */
    public static function styleLabel(array $section): ?string
    {
        $style = $section['style'] ?? null;

        return is_string($style) && $style !== '' ? (string) __('content::admin_themes.sections.style', ['style' => str_replace('-', ' ', $style)]) : null;
    }
}
