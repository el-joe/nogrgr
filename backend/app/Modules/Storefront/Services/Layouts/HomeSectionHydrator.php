<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Services\Banners\BannerCatalog;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\ThemeSectionLibrary;

/**
 * Applies page-builder settings to the home sections a theme renders (VEN-032): title /
 * subtitle in the shopper's language (`title_text` / `subtitle_text`, theme text when the
 * store wrote none in that language), layout options (style / rows / heading), the product
 * source (`cards`) and picked banners (`banners`, in the vendor's order). Sections without
 * settings are returned unchanged, so the theme design stays the default.
 */
class HomeSectionHydrator
{
    /** @var array<string, list<BannerData>> */
    private array $banners = [];

    public function __construct(
        private readonly SectionProductResolver $products,
        private readonly StorefrontContext $context,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function hydrate(ThemeFamily $family, array $sections): array
    {
        return array_map(fn (array $section): array => $this->section($family, $section), $sections);
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public function section(ThemeFamily $family, array $section): array
    {
        $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];

        if ($settings === []) {
            return $section;
        }

        $locale = app()->getLocale();

        foreach (['title' => 'title_text', 'subtitle' => 'subtitle_text'] as $field => $target) {
            if (is_string($settings[$field][$locale] ?? null) && $settings[$field][$locale] !== '') {
                $section[$target] = $settings[$field][$locale];
            }
        }

        foreach (ThemeSectionLibrary::schema($family, $section) as $field) {
            $name = (string) $field['name'];

            if (($field['layout'] ?? false) && isset($settings[$name]) && in_array($settings[$name], $field['options'], true)) {
                $section[$name] = $settings[$name];
            }

            if ($field['type'] === 'banners' && isset($settings[$name])) {
                $section['banners'] = $this->pick((string) $field['placement'], (array) $settings[$name]);
            }
        }

        if (ThemeSectionLibrary::isProductSection($section) && ($cards = $this->products->cards($settings, is_string($section['data'] ?? null) ? $section['data'] : (string) $section['type'])) !== null) {
            $section['cards'] = $cards;
        }

        return $section;
    }

    /**
     * Picked banners ("central:5" / "vendor:3") of a placement that are live in the country.
     *
     * @param  list<string>  $keys
     * @return list<BannerData>
     */
    public function pick(?string $placement, array $keys): array
    {
        $cacheKey = $placement ?? '*';
        $this->banners[$cacheKey] ??= $placement !== null
            ? app(BannerCatalog::class)->forPlacement($placement, $this->context->countryId(), app()->getLocale())
            : [...app(BannerCatalog::class)->forPlacement('home.hero', $this->context->countryId(), app()->getLocale()), ...app(BannerCatalog::class)->forPlacement('home.promo', $this->context->countryId(), app()->getLocale())];
        $byKey = [];

        foreach ($this->banners[$cacheKey] as $banner) {
            $byKey[$banner->source.':'.$banner->id] = $banner;
        }

        return array_values(array_filter(array_map(static fn (string $key): ?BannerData => $byKey[$key] ?? null, $keys)));
    }
}
