<?php

use Symfony\Component\Finder\Finder;

/*
| RQA-ADM-003: a data-ajax form without `novalidate` lets the browser block the submit
| with its own (English-only) validation bubble, so our translated inline 422 errors
| never show. Every data-ajax form opts out of native validation.
*/

/**
 * Forms allowed to keep native validation: path => reason. Keep empty unless needed.
 *
 * @var array<string, string>
 */
const AJAX_FORMS_NATIVE_VALIDATION_ALLOWED = [];

it('marks every data-ajax form novalidate so the server errors are shown', function (): void {
    $root = dirname(__DIR__, 3);
    $violations = [];

    $finder = (new Finder)->files()->name('*.blade.php')->in([$root.'/resources/views', ...glob($root.'/app/Modules/*/resources/views', GLOB_ONLYDIR)]);

    foreach ($finder as $file) {
        $contents = $file->getContents();
        preg_match_all('/<form\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', $contents, $forms, PREG_OFFSET_CAPTURE);
        $path = str_replace($root.'/', '', $file->getRealPath());

        foreach ($forms[0] as [$tag, $offset]) {
            if (preg_match('~\sdata-ajax(?=[\s>=/])~', $tag) === 1 && preg_match('/\bnovalidate\b/', $tag) !== 1 && ! isset(AJAX_FORMS_NATIVE_VALIDATION_ALLOWED[$path])) {
                $violations[] = $path.':'.(substr_count(substr($contents, 0, $offset), "\n") + 1);
            }
        }
    }

    expect($violations)->toBeEmpty();
});
