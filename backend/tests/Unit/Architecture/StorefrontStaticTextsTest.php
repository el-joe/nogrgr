<?php

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/*
| Decision #45: storefront static text comes from the store's database through
| __st() / @st (StaticTextResolver), never from lang/* — so no storefront view may use
| __('storefront::…') / trans('storefront::…') / @lang('storefront::…'), and the
| Storefront module ships no lang directory (its base set is a data file).
*/

it('keeps storefront views on the static text resolver', function (): void {
    $root = dirname(__DIR__, 3);

    $finder = (new Finder)
        ->files()
        ->in(array_filter([
            $root.'/resources/views/components/storefront',
            $root.'/resources/views/storefront',
            $root.'/app/Modules',
        ], 'is_dir'))
        ->name('*.blade.php');

    $violations = [];

    foreach ($finder as $file) {
        $isStorefrontView = str_contains($file->getPathname(), '/storefront/') || str_contains($file->getPathname(), '/Storefront/');

        if ($isStorefrontView && preg_match('/(?:__|trans|trans_choice|@lang)\(\s*[\'"]storefront::/', $file->getContents()) === 1) {
            $violations[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('ships the storefront base texts as data, not as a lang namespace', function (): void {
    $module = dirname(__DIR__, 3).'/app/Modules/Storefront';

    expect(is_dir($module.'/resources/lang'))->toBeFalse()
        ->and(is_file($module.'/Database/Data/static-texts/en/shell.php'))->toBeTrue()
        ->and(is_file($module.'/Database/Data/static-texts/ar/shell.php'))->toBeTrue();
});

it('has the same base keys in arabic and english', function (): void {
    $base = dirname(__DIR__, 3).'/app/Modules/Storefront/Database/Data/static-texts';
    $keys = function (string $locale) use ($base): array {
        $all = [];

        foreach (glob($base.'/'.$locale.'/*.php') ?: [] as $file) {
            foreach (Arr::dot(require $file) as $key => $value) {
                $all[] = basename($file, '.php').'.'.$key;
            }
        }

        sort($all);

        return $all;
    };

    expect($keys('ar'))->toBe($keys('en'));
});
