<?php

use Symfony\Component\Finder\Finder;

/*
| Client rule (2026-11-26, decision #88): "make images fit container not cover". Every content
| image (products, variants, categories, banners / heroes, logos, order and cart thumbs, blog,
| theme previews, avatars) shows whole with object-contain inside its Figma-sized box, on the
| neutral `bg-media` / var(--color-media) letterbox. Cropping with `object-cover` or
| `object-fit: cover` is not allowed in views, JS or CSS.
|
| Allowlist: only purely decorative CSS backgrounds may crop, and those use
| `background-size: cover` (band art, shell textures), which this test does not match. Add a
| "path" => "reason" entry here only for such a decorative rule written with object-fit.
*/

/** @var array<string, string> $allowlist */
$allowlist = [];

it('never crops content images with object-cover or object-fit: cover', function () use ($allowlist): void {
    $base = dirname(__DIR__, 3);
    $files = Finder::create()->files()
        ->in([$base.'/resources/views', $base.'/resources/js', $base.'/resources/css', $base.'/app/Modules'])
        ->name(['*.blade.php', '*.js', '*.css']);

    $offenders = [];

    foreach ($files as $file) {
        $relative = str_replace($base.'/', '', $file->getRealPath());

        if (array_key_exists($relative, $allowlist)) {
            continue;
        }

        foreach (preg_split('/\R/', $file->getContents()) ?: [] as $index => $line) {
            if (preg_match('/(?<![\w-])object-cover(?![\w-])|object-fit\s*:\s*cover/', $line) === 1) {
                $offenders[] = $relative.':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('defines the neutral letterbox token for contained images', function (): void {
    $tokens = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/tokens/base.css');

    expect($tokens)->toContain('--color-media: var(--nog-media);', '--nog-media: var(--nog-subtle);');
});
