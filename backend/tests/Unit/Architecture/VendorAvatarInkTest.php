<?php

/*
| RQA-VEN-004: vendor table cells recolour .font-medium / .text-ink to the table greys and
| title black; the avatar disc (bg-inverse + font-medium) must keep its own on-inverse
| initials, otherwise Settings › Staff shows solid black circles. Every such rule in
| vendor-shell.css must skip .ui-avatar, and the avatar component must carry that class.
*/

it('keeps avatar initials out of the vendor table cell colour overrides', function (): void {
    $root = dirname(__DIR__, 3);
    $avatar = file_get_contents($root.'/resources/views/components/ui/avatar.blade.php');

    expect($avatar)->toContain("'ui-avatar ")
        ->and($avatar)->toContain('text-on-inverse');

    $css = (string) file_get_contents($root.'/resources/css/panels/vendor-shell.css');
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

    $offending = [];

    foreach ($rules as [, $selector, $body]) {
        $selector = trim(preg_replace('/\/\*.*?\*\//s', '', $selector) ?? '');

        if (! str_contains($selector, 'table.dataTable tbody td') || ! preg_match('/(^|[\s;])color\s*:/', $body)) {
            continue;
        }

        if (preg_match('/\.font-medium|\.text-ink\b/', $selector) && ! str_contains($selector, '.ui-avatar')) {
            $offending[] = $selector;
        }
    }

    expect($offending)->toBe([]);
});

it('defines the on-inverse ink for the vendor surface in light and dark', function (): void {
    $tokens = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/tokens/vendor.css');

    expect(substr_count($tokens, '--nog-on-inverse:'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($tokens, '--nog-inverse:'))->toBeGreaterThanOrEqual(2);
});
