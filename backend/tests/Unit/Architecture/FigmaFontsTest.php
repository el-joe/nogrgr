<?php

use App\Shared\Support\FontPreload;

const FIGMA_FONTS_ROOT = __DIR__.'/../../..';

/*
 * Every surface loads and declares the font families of its Figma pages (decision #89):
 * Latin + Arabic body stacks as tokens, the @fontsource files that back them (self-hosted,
 * no CDN) and the per-family accent fonts where the design uses them.
 */

/**
 * Concatenate a CSS entry with every local file it @imports (recursively).
 */
function surfaceCss(string $entry): string
{
    $seen = [];
    $read = function (string $path) use (&$read, &$seen): string {
        $real = realpath($path);
        if ($real === false || isset($seen[$real])) {
            return '';
        }
        $seen[$real] = true;
        $css = (string) file_get_contents($real);
        preg_match_all("/@import\s+'(\.{1,2}\/[^']+)'/", $css, $matches);
        foreach ($matches[1] as $import) {
            $css .= "\n".$read(dirname($real).'/'.$import);
        }

        return $css;
    };

    return $read(FIGMA_FONTS_ROOT.'/resources/css/'.$entry.'.css');
}

dataset('surfaces', [
    'admin' => ['admin', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', []],
    'affiliate' => ['affiliate', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', []],
    'owner' => ['owner', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', []],
    'vendor' => ['vendor', 'Outfit', 'IBM Plex Sans Arabic', 'outfit', 'ibm-plex-sans-arabic', []],
    'central' => ['central', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', ['Inter' => 'inter']],
    'ecommet' => ['storefront-ecommet', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', ['Tajawal' => 'tajawal', 'Inter' => 'inter']],
    'elora' => ['storefront-elora', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', ['Inter' => 'inter']],
    'souqify' => ['storefront-souqify', 'Outfit', 'Noto Sans Arabic', 'outfit', 'noto-sans-arabic', ['Inter' => 'inter']],
]);

it('declares and self-hosts the Figma fonts of the surface', function (string $entry, string $latin, string $arabic, string $latinPackage, string $arabicPackage, array $accents): void {
    $css = surfaceCss($entry);

    expect($css)
        ->toMatch("/--nog-font-latin:\s*'".preg_quote($latin, '/')."'/")
        ->toMatch("/--nog-font-arabic:\s*'".preg_quote($arabic, '/')."'/")
        ->toContain("@import '@fontsource/{$latinPackage}/400.css'")
        ->toContain("@import '@fontsource/{$arabicPackage}/arabic-400.css'")
        ->not->toMatch("#@import\\s+(url\\()?['\"]?https?://#i")
        ->not->toMatch('#fonts\\.(googleapis|gstatic|bunny)\\.#i');

    // The last token declaration wins: the surface's own family, not the shared default.
    preg_match_all("/--nog-font-arabic:\s*'([^']+)'/", $css, $declared);
    expect(end($declared[1]))->toBe($arabic);

    foreach ($accents as $family => $package) {
        expect($css)
            ->toContain("'{$family}'")
            ->toMatch("#@import '@fontsource/{$package}/latin-\d00\.css'#");
    }
})->with('surfaces');

it('switches the body stack to the Arabic family under rtl', function (): void {
    expect(surfaceCss('admin'))->toMatch("/\[dir='rtl'\]\s*\{\s*--nog-font-sans:\s*var\(--nog-font-arabic\)/");
});

it('no longer ships the old shared font bundle', function (): void {
    expect(FIGMA_FONTS_ROOT.'/resources/css/shared/fonts.css')->not->toBeFile();
});

it('pins every font package to an exact version', function (): void {
    $package = json_decode((string) file_get_contents(FIGMA_FONTS_ROOT.'/package.json'), true);
    $fonts = array_filter($package['dependencies'] ?? [], fn (string $name): bool => str_starts_with($name, '@fontsource'), ARRAY_FILTER_USE_KEY);

    expect($fonts)->toHaveKeys(['@fontsource/outfit', '@fontsource/noto-sans-arabic', '@fontsource/ibm-plex-sans-arabic', '@fontsource/inter', '@fontsource/tajawal']);
    foreach ($fonts as $version) {
        expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
    }
});

it('preloads the body woff2 for the surface and direction', function (): void {
    expect(FontPreload::path('admin', false))->toBe(FontPreload::LATIN)
        ->and(FontPreload::path('central', true))->toBe(FontPreload::ARABIC)
        ->and(FontPreload::path('storefront', true))->toBe(FontPreload::ARABIC)
        ->and(FontPreload::path('vendor', true))->toBe(FontPreload::ARABIC_VENDOR);

    foreach ([FontPreload::LATIN, FontPreload::ARABIC, FontPreload::ARABIC_VENDOR] as $file) {
        expect(FIGMA_FONTS_ROOT.'/'.$file)->toBeFile();
    }
});
