<?php

use Symfony\Component\Finder\Finder;
use Tests\Support\AppClasses;

/*
| The vendor panel runs on two hosts from one route set. Links must go through
| vendor_route() / VendorPanel::route(); naming vendor.* or vendor-central.* routes
| directly breaks one of the modes.
*/

it('never names vendor panel routes directly in app code or views', function (): void {
    $pattern = '/(?:\broute|to_route|->route|URL::route|redirect\(\)->route)\s*\(\s*[\'"](?:vendor|vendor-central)\./';

    $files = Finder::create()
        ->files()
        ->name(['*.php'])
        ->in([AppClasses::appPath(), dirname(AppClasses::appPath()).'/resources/views'])
        ->notPath(['Shared/Routing']);

    $offenders = [];

    foreach ($files as $file) {
        if (preg_match_all($pattern, $file->getContents(), $matches) > 0) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use vendor_route(\'…\') for vendor panel links.');
});
