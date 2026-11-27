<?php

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/*
| A translation with plural forms ("{0} …|{1} …|[2,*] …") read through __() / trans() /
| @lang prints the raw choice string. Every literal key whose English value has plural
| syntax must be read with trans_choice() / @choice.
*/

/**
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function pluralTranslationKeys(array $values, string $prefix): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        if (is_array($value)) {
            $keys = [...$keys, ...pluralTranslationKeys($value, $prefix.$key.'.')];
        } elseif (is_string($value) && str_contains($value, '|') && preg_match('/(^|\|)\s*(\{\d+\}|\[\d+,(\d+|\*)\])/', $value) === 1) {
            $keys[] = $prefix.$key;
        }
    }

    return $keys;
}

it('reads plural translations only through trans_choice', function (): void {
    $root = dirname(__DIR__, 3);
    $plural = [];

    foreach (glob($root.'/app/Modules/*/resources/lang/en/*.php') ?: [] as $file) {
        $module = Str::kebab(basename(dirname($file, 4)));
        $plural = [...$plural, ...pluralTranslationKeys((array) require $file, $module.'::'.basename($file, '.php').'.')];
    }

    foreach (glob($root.'/lang/en/*.php') ?: [] as $file) {
        $plural = [...$plural, ...pluralTranslationKeys((array) require $file, basename($file, '.php').'.')];
    }

    expect($plural)->not->toBe([]);
    $plural = array_flip($plural);

    $finder = (new Finder)->files()->in(array_filter([$root.'/app', $root.'/resources/views'], 'is_dir'))->name(['*.php']);
    $violations = [];

    foreach ($finder as $file) {
        preg_match_all('/(?<![\w>:])(?:__|trans|@lang)\(\s*[\'"]([^\'"]+)[\'"]/', $file->getContents(), $matches);

        foreach ($matches[1] as $key) {
            if (isset($plural[$key])) {
                $violations[] = $file->getRelativePathname().': '.$key;
            }
        }
    }

    expect($violations)->toBe([], 'Use trans_choice() for plural translations: '.implode(', ', $violations));
});
