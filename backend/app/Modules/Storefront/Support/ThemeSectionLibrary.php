<?php

namespace App\Modules\Storefront\Support;

use App\Modules\Storefront\Enums\PageSectionType;
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
        ['type' => 'promos'],
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

    /** Product sections without a `data` key that still show product cards. */
    private const PRODUCT_TYPES = ['deals'];

    /** Sections whose banners the vendor may pick (type => banner placement). */
    private const BANNER_TYPES = ['hero' => 'home.hero', 'promos' => 'home.promo'];

    /** Layout options a section may switch between (taken from every variant of its family). */
    private const LAYOUT_OPTIONS = ['style', 'rows', 'heading'];

    public const LIMIT_MIN = 4;

    public const LIMIT_MAX = 24;

    public const LIMIT_DEFAULT = 12;

    /**
     * Page-builder settings schema of a home section (VEN-032, decision #117 b). Every field
     * is honoured by the storefront section it belongs to:
     *   text / textarea / richtext (i18n per store language), number (min/max/default),
     *   select (options), source (product source + category / badge / manual products),
     *   banners (placement), categories, faq, image, url.
     *
     * @param  array<string, mixed>  $section  library definition
     * @return list<array<string, mixed>>
     */
    public static function schema(ThemeFamily $family, array $section): array
    {
        $type = (string) $section['type'];
        $fields = [];

        if (self::isProductSection($section) || ($family === ThemeFamily::Ecommet && $type === 'best-grid')) {
            $fields[] = ['name' => 'title', 'type' => 'text', 'i18n' => true, 'max' => 120];
        }

        if (self::isProductSection($section) && $family !== ThemeFamily::Ecommet) {
            $fields[] = ['name' => 'subtitle', 'type' => 'text', 'i18n' => true, 'max' => 200];
        }

        if (self::isProductSection($section)) {
            $fields[] = ['name' => 'source', 'type' => 'source', 'default' => 'default'];
            $fields[] = ['name' => 'limit', 'type' => 'number', 'min' => self::LIMIT_MIN, 'max' => self::LIMIT_MAX, 'default' => self::LIMIT_DEFAULT];
        }

        if ($family === ThemeFamily::Elora && $type === 'flash' && ($section['style'] ?? null) === 'band') {
            // "Use Code: …" chip of the v1 flash sale (Figma 1:15595); hidden while empty.
            $fields[] = ['name' => 'code', 'type' => 'text', 'i18n' => true, 'max' => 40];
        }

        if (isset(self::BANNER_TYPES[$type])) {
            $fields[] = ['name' => 'banners', 'type' => 'banners', 'placement' => self::BANNER_TYPES[$type]];
        }

        foreach (self::LAYOUT_OPTIONS as $option) {
            $values = self::optionValues($family, $section, $option);

            if (count($values) > 1) {
                $fields[] = ['name' => $option, 'type' => 'select', 'layout' => true, 'options' => $values, 'default' => (string) $section[$option]];
            }
        }

        return $fields;
    }

    /**
     * Settings schema of a store-page section type.
     *
     * @return list<array<string, mixed>>
     */
    public static function pageSchema(PageSectionType $type): array
    {
        $title = ['name' => 'title', 'type' => 'text', 'i18n' => true, 'max' => 120];

        return match ($type) {
            PageSectionType::RichText => [$title, ['name' => 'body', 'type' => 'richtext', 'i18n' => true, 'max' => 20000]],
            PageSectionType::Banner => [['name' => 'banners', 'type' => 'banners', 'placement' => null], ['name' => 'style', 'type' => 'select', 'layout' => true, 'options' => ['contained', 'full'], 'default' => 'contained']],
            PageSectionType::ProductRail => [$title, ['name' => 'subtitle', 'type' => 'text', 'i18n' => true, 'max' => 200], ['name' => 'source', 'type' => 'source', 'default' => 'new', 'no_default' => true], ['name' => 'limit', 'type' => 'number', 'min' => self::LIMIT_MIN, 'max' => self::LIMIT_MAX, 'default' => self::LIMIT_DEFAULT]],
            PageSectionType::CategoryGrid => [$title, ['name' => 'categories', 'type' => 'categories'], ['name' => 'limit', 'type' => 'number', 'min' => 2, 'max' => 24, 'default' => 8]],
            PageSectionType::Faq => [$title, ['name' => 'items', 'type' => 'faq', 'max' => 20]],
            PageSectionType::ImageText => [$title, ['name' => 'body', 'type' => 'textarea', 'i18n' => true, 'max' => 2000], ['name' => 'image', 'type' => 'image'], ['name' => 'image_side', 'type' => 'select', 'layout' => true, 'options' => ['start', 'end'], 'default' => 'start'], ['name' => 'button', 'type' => 'text', 'i18n' => true, 'max' => 60], ['name' => 'url', 'type' => 'url', 'max' => 500]],
        };
    }

    /**
     * Rails / product cards the vendor may re-source.
     *
     * @param  array<string, mixed>  $section
     */
    public static function isProductSection(array $section): bool
    {
        return (isset($section['data']) && is_string($section['data'])) || in_array($section['type'], self::PRODUCT_TYPES, true);
    }

    /**
     * Distinct values an option takes for this section type across the family's variants
     * (empty when the section has no such option).
     *
     * @param  array<string, mixed>  $section
     * @return list<string>
     */
    private static function optionValues(ThemeFamily $family, array $section, string $option): array
    {
        if (! isset($section[$option]) || ! is_string($section[$option]) || $section[$option] === '') {
            return [];
        }

        $count = match ($family) {
            ThemeFamily::Elora => EloraVariants::COUNT,
            ThemeFamily::Souqify => SouqifyVariants::COUNT,
            ThemeFamily::Ecommet => 1,
        };
        $values = [];

        for ($variant = 1; $variant <= $count; $variant++) {
            foreach (self::sections($family, $variant) as $candidate) {
                if ($candidate['type'] === $section['type'] && isset($candidate[$option]) && is_string($candidate[$option]) && $candidate[$option] !== '') {
                    $values[$candidate[$option]] = true;
                }
            }
        }

        return array_keys($values);
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
