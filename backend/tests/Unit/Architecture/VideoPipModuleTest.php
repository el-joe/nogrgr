<?php

/*
| The floating product-video popup (#114) is one shared Vite module with an adapter per
| storefront family, loaded by the shared product page script of every family.
*/

it('registers the shared video popup module for every storefront family', function (): void {
    $root = dirname(__DIR__, 3);
    $module = (string) file_get_contents($root.'/resources/js/storefront/core/video-pip.js');
    $product = (string) file_get_contents($root.'/resources/js/storefront/catalog/product.js');

    expect($module)->toContain('export function mountVideoPip')
        ->toContain('export function initVideoPip')
        ->toContain('IntersectionObserver')
        ->toContain('prefers-reduced-motion')
        ->not->toContain('.style.')
        ->not->toContain('cssText')
        ->and($product)->toContain("from '../core/video-pip.js'")->toContain('initVideoPip(page)');

    foreach (['ecommet', 'elora', 'souqify'] as $family) {
        expect($module)->toMatch('/\b'.$family.':\s*\w+Adapter/');
        expect((string) file_get_contents($root.'/resources/js/storefront/'.$family.'/index.js'))->toContain("import('../catalog/product')");
        expect((string) file_get_contents($root.'/app/Modules/Storefront/resources/views/themes/'.$family.'/pages/product.blade.php'))
            ->toContain('data-video-pip')
            ->toContain('x-sf::gallery.video ');
    }

    expect((string) file_get_contents($root.'/resources/css/storefront/sf.css'))->toContain('.sf-video-pip');
});
