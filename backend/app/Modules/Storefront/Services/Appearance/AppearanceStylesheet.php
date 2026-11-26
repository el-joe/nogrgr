<?php

namespace App\Modules\Storefront\Services\Appearance;

use App\Modules\Storefront\DTOs\Appearance\AppearanceColorsData;
use App\Modules\Storefront\DTOs\Appearance\PromoBarData;
use App\Modules\Storefront\Support\HexColor;

/**
 * The store's design tokens as a small same-origin stylesheet (VEN-034b/c, decision #91):
 * brand colour → the theme family's primary / CTA / accent custom properties (also over
 * the home-variant blocks), chosen fonts → --nog-font-latin / --nog-font-arabic, promo
 * bar colours → --sf-promo-*. Served by storefront.appearance.css; no inline styles.
 */
class AppearanceStylesheet
{
    /**
     * Theme custom properties that follow the brand colour, per family.
     *
     * @var array<string, list<string>>
     */
    private const FAMILY_PRIMARY = [
        'ecommet' => ['--sf-header', '--eco-red'],
        'elora' => ['--sf-cta', '--elo-weight', '--elo-see-all', '--elo-cta-bg', '--elo-tag-bg'],
        'souqify' => ['--sf-cta', '--sf-info', '--sq-tag', '--sq-shape', '--sq-see-all'],
    ];

    public function build(string $family, AppearanceColorsData $colors, PromoBarData $promo): string
    {
        $root = [];
        $variant = [];

        if ($colors->primary !== null) {
            $brand = HexColor::from($colors->primary);
            $tokens = [
                '--nog-primary' => $brand->hex(),
                '--nog-primary-hover' => $brand->darken(0.12)->hex(),
                '--sf-accent' => $brand->hex(),
                '--sf-accent-subtle' => $brand->lighten(0.9)->hex(),
            ];

            foreach (self::FAMILY_PRIMARY[$family] ?? [] as $property) {
                $tokens[$property] = $brand->hex();
            }

            if (in_array('--sf-cta', self::FAMILY_PRIMARY[$family] ?? [], true)) {
                $tokens['--sf-cta-hover'] = $brand->darken(0.12)->hex();
                $tokens['--sf-on-cta'] = $brand->readableInk();
            }

            if ($family === 'elora') {
                $tokens['--elo-cta-ink'] = $brand->readableInk();
            }

            if ($family === 'souqify') {
                $tokens['--sq-price'] = $brand->darken(0.15)->hex();
            }

            $root += $tokens;
            $variant += $tokens;
        }

        if ($colors->fontLtr !== null) {
            $root['--nog-font-latin'] = "'".$colors->fontLtr->family()."', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
        }

        if ($colors->fontRtl !== null) {
            $root['--nog-font-arabic'] = "'".$colors->fontRtl->family()."', ui-sans-serif, system-ui, sans-serif";
        }

        $background = HexColor::from($promo->background);
        $root['--sf-promo-bg'] = $background->hex();
        $root['--sf-promo-ink'] = $background->readableInk();

        $css = "/* Store appearance tokens (Vendor › Appearance) */\n".$this->block('html:root', $root);

        if ($variant !== []) {
            $css .= $this->block('html .sf-page[data-theme-variant]', $variant);
        }

        return $css;
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function block(string $selector, array $properties): string
    {
        $lines = [];

        foreach ($properties as $name => $value) {
            // Values come from validated hex colours and the StoreFont enum only.
            $lines[] = '    '.$name.': '.str_replace(['<', '>', '{', '}', ';'], '', $value).';';
        }

        return $selector." {\n".implode("\n", $lines)."\n}\n";
    }
}
