<?php

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/*
| The bundled default Open Graph image (1200×630, resources/images/central/og-default.webp)
| is the SEO fallback of every central page without its own image.
*/

it('ships a 1200x630 default Open Graph image', function (): void {
    $size = getimagesize(base_path('resources/images/central/og-default.webp'));

    expect($size)->not->toBeFalse()
        ->and([$size[0], $size[1]])->toBe([1200, 630]);
});

it('uses the default Open Graph image on central pages', function (): void {
    // Tests run without the Vite manifest: resolve assets to a predictable build URL.
    $this->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            return new HtmlString('');
        }

        public function asset($asset, $buildDirectory = null): string
        {
            return '/build/'.basename((string) $asset);
        }
    });

    $this->get($this->centralUrl('/'))
        ->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee('/build/og-default.webp', false)
        ->assertSee('summary_large_image', false);
});
