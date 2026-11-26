<?php

use App\Modules\Storefront\DTOs\Appearance\AppearanceColorsData;
use App\Modules\Storefront\DTOs\Appearance\PromoBarData;
use App\Modules\Storefront\Enums\SocialNetwork;
use App\Modules\Storefront\Enums\StoreFont;
use App\Modules\Storefront\Enums\TrackingPixel;
use App\Modules\Storefront\Services\Appearance\AppearanceStylesheet;
use App\Modules\Storefront\Support\BrandPalette;
use App\Modules\Storefront\Support\HexColor;

/*
| Store appearance tokens (VEN-034b/c): colour maths, the per-family custom properties of the
| appearance stylesheet, fonts, promo colours; social / pixel input rules (VEN-034f / 083).
*/

it('derives hover, tint and a readable text colour from the brand colour', function (): void {
    $brand = HexColor::from('#FF4D00');

    expect($brand->hex())->toBe('#ff4d00')
        ->and($brand->darken(0.12)->hex())->toBe('#e04400')
        ->and($brand->lighten(0.9)->hex())->toBe('#ffede6')
        ->and(HexColor::from('#ffd428')->readableInk())->toBe('#121212')
        ->and(HexColor::from('#0159ed')->readableInk())->toBe('#ffffff')
        ->and(HexColor::isValid('#12345'))->toBeFalse()
        ->and(HexColor::isValid('#12345g'))->toBeFalse();

    expect(fn () => HexColor::from('red'))->toThrow(InvalidArgumentException::class);
});

it('offers a 36 colour palette of valid hex colours', function (): void {
    expect(BrandPalette::COLORS)->toHaveCount(36)
        ->and(array_unique(BrandPalette::COLORS))->toHaveCount(36)
        ->and(array_filter(BrandPalette::COLORS, fn (string $hex): bool => ! HexColor::isValid($hex)))->toBe([]);
});

it('maps the brand colour to each theme family and over the home variants', function (string $family, array $properties, array $absent): void {
    $css = (new AppearanceStylesheet)->build($family, new AppearanceColorsData('#8a38f5', StoreFont::Inter, StoreFont::IbmPlexSansArabic), new PromoBarData);

    foreach ($properties as $property) {
        expect($css)->toContain($property.': #8a38f5;');
    }

    foreach ($absent as $property) {
        expect($css)->not->toContain($property.':');
    }

    expect($css)->toContain('html:root {')
        ->toContain('html .sf-page[data-theme-variant] {')
        ->toContain("--nog-font-latin: 'Inter'")
        ->toContain("--nog-font-arabic: 'IBM Plex Sans Arabic'")
        ->toContain('--sf-promo-bg: #0b0b0b;')
        ->not->toContain('<')->not->toContain('}}');
})->with([
    'ecommet' => ['ecommet', ['--nog-primary', '--sf-header', '--eco-red', '--sf-accent'], ['--sf-cta']],
    'elora' => ['elora', ['--nog-primary', '--sf-cta', '--elo-cta-bg', '--elo-see-all'], ['--eco-red']],
    'souqify' => ['souqify', ['--nog-primary', '--sf-cta', '--sq-tag', '--sq-shape'], ['--eco-red', '--elo-cta-bg']],
]);

it('only writes promo colours when the store keeps the theme colours and fonts', function (): void {
    $css = (new AppearanceStylesheet)->build('elora', new AppearanceColorsData, new PromoBarData(true, ['en' => 'Hi'], null, '#ffd428'));

    expect($css)->toContain('--sf-promo-bg: #ffd428;')->toContain('--sf-promo-ink: #121212;')
        ->not->toContain('--nog-primary')->not->toContain('--nog-font')->not->toContain('data-theme-variant');
});

it('reads stored settings defensively', function (): void {
    expect(AppearanceColorsData::fromSetting(['primary' => 'javascript:x', 'font_ltr' => 'comic-sans'])->isDefault())->toBeTrue()
        ->and(PromoBarData::fromSetting(['enabled' => true, 'text' => [], 'background' => 'red'])->isVisible())->toBeFalse()
        ->and(PromoBarData::fromSetting(['enabled' => true, 'text' => ['ar' => 'مرحبا']])->textFor('en'))->toBe('مرحبا')
        ->and(PromoBarData::fromSetting(null)->background)->toBe('#0b0b0b');
});

it('limits fonts to their writing direction', function (): void {
    expect(array_keys(StoreFont::ltrOptions()))->toBe(['outfit', 'inter', 'manrope', 'tajawal'])
        ->and(array_keys(StoreFont::rtlOptions()))->toBe(['noto-sans-arabic', 'ibm-plex-sans-arabic', 'tajawal']);

    foreach (StoreFont::cases() as $font) {
        expect(dirname(__DIR__, 3).'/'.$font->cssEntry())->toBeFile();
    }
});

it('accepts social profiles only on the network\'s own hosts', function (): void {
    expect(SocialNetwork::Instagram->accepts('https://www.instagram.com/megz'))->toBeTrue()
        ->and(SocialNetwork::Instagram->accepts('https://instagram.com.evil.test/megz'))->toBeFalse()
        ->and(SocialNetwork::X->accepts('https://twitter.com/megz'))->toBeTrue()
        ->and(SocialNetwork::Whatsapp->accepts('https://wa.me/966500000000'))->toBeTrue()
        ->and(SocialNetwork::Tiktok->accepts('https://www.youtube.com/@megz'))->toBeFalse();

    foreach (SocialNetwork::cases() as $network) {
        expect(dirname(__DIR__, 3).'/resources/svg/icons/'.$network->icon().'.svg')->toBeFile();
    }
});

it('normalises and validates the pixel ids of each network', function (): void {
    expect(TrackingPixel::Meta->normalize('1022 3344 5566'))->toBe('102233445566')
        ->and(TrackingPixel::Ga4->normalize('g-megz1234'))->toBe('G-MEGZ1234')
        ->and(preg_match(TrackingPixel::Ga4->pattern(), 'UA-1234-1'))->toBe(0);

    foreach (TrackingPixel::cases() as $pixel) {
        expect(preg_match($pixel->pattern(), $pixel->normalize($pixel->placeholder())))->toBe(1);
    }
});
