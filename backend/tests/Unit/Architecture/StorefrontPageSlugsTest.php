<?php

use App\Modules\Storefront\Enums\SystemStorePage;
use Symfony\Component\Finder\Finder;

/*
| QA-FINAL-003: storefront links to CMS pages by a literal slug must use a slug every
| store actually has (SystemStorePage), otherwise the link is a dead 404 ("returns"
| instead of "returns-policy" on the cart / product trust rows and the privacy popup).
*/

it('links storefront CMS pages only by system page slugs', function (): void {
    $slugs = array_map(static fn (SystemStorePage $page): string => $page->value, SystemStorePage::cases());
    $bad = [];

    $files = Finder::create()->files()->name('*.blade.php')->in([
        dirname(__DIR__, 3).'/app/Modules/Storefront/resources/views',
        dirname(__DIR__, 3).'/resources/views/components/storefront',
    ]);

    foreach ($files as $file) {
        preg_match_all("/storefront\\.pages\\.show',\\s*\\['slug'\\s*=>\\s*'([^']+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $slug) {
            if (! in_array($slug, $slugs, true)) {
                $bad[] = $file->getRelativePathname().': '.$slug;
            }
        }
    }

    expect($bad)->toBe([]);
});
