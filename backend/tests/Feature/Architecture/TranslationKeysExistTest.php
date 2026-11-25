<?php

use Illuminate\Support\Facades\Lang;
use Symfony\Component\Finder\Finder;

/*
| Every literal module translation key used in code (`__('module::file.key')`,
| `trans()`, `trans_choice()`, `@lang`) must exist in both en and ar, so no page
| shows a raw key (QA-P5-002: notifications::panel.inbox.vendor_empty_description).
| Dynamic keys built by concatenation (literal ending in "_" or ".") are skipped.
*/

it('resolves every literal module translation key in en and ar', function (): void {
    $finder = (new Finder)
        ->files()
        ->in([app_path('Modules'), resource_path('views')])
        ->name(['*.blade.php', '*.php'])
        ->notPath(['resources/lang']);

    $missing = [];

    foreach ($finder as $file) {
        if (! preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*[\'"]([a-z_\-]+::[a-z0-9_\-.]+)[\'"]\s*[,)]/', $file->getContents(), $matches)) {
            continue;
        }

        foreach (array_unique($matches[1]) as $key) {
            foreach (['en', 'ar'] as $locale) {
                if (! Lang::has($key, $locale, false)) {
                    $missing[] = $locale.' '.$key.' ('.$file->getRelativePathname().')';
                }
            }
        }
    }

    expect($missing)->toBe([]);
});
