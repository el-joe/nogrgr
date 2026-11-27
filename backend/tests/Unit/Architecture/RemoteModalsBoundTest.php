<?php

use Symfony\Component\Finder\Finder;

/*
| QA-FINAL-005: a page whose Blade has [data-remote-modal] openers only works when the
| page module loaded for its data-page (panels/<panel>/index.js registry) calls
| bindRemoteModals() — directly or through a module it imports. The admin Translations
| / Key locks page shipped without it, so Add key, New file, View / Edit key and
| "Translate with AI" did nothing.
*/

/**
 * @return array<string, list<string>> data-page => absolute module paths
 */
function remoteModalPageRegistry(): array
{
    $registry = [];

    foreach (glob(dirname(__DIR__, 3).'/resources/js/panels/*/index.js') ?: [] as $index) {
        preg_match_all("/'?([a-z0-9-]+)'?:\\s*\\(\\)\\s*=>\\s*import\\('([^']+)'\\)/", (string) file_get_contents($index), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $page, $module]) {
            $registry[$page][] = (string) realpath(dirname($index).'/'.$module);
        }
    }

    return $registry;
}

/**
 * @param  array<string, true>  $seen
 */
function remoteModalModuleBinds(string $file, array &$seen = []): bool
{
    if ($file === '' || isset($seen[$file]) || ! is_file($file)) {
        return false;
    }

    $seen[$file] = true;
    $source = (string) file_get_contents($file);

    if (! str_ends_with($file, 'remote-modal.js') && str_contains($source, 'bindRemoteModals(')) {
        return true;
    }

    preg_match_all("/(?:from\\s+|import\\()'(\\.[^']+)'/", $source, $imports);

    foreach ($imports[1] as $import) {
        $path = dirname($file).'/'.$import;
        $path = str_ends_with($path, '.js') ? $path : $path.'.js';

        if (remoteModalModuleBinds((string) realpath($path), $seen)) {
            return true;
        }
    }

    return false;
}

it('binds remote modals on every data-page that has remote-modal openers', function (): void {
    $registry = remoteModalPageRegistry();
    $unbound = [];

    $views = Finder::create()->files()->name('*.blade.php')->in([dirname(__DIR__, 3).'/app/Modules', dirname(__DIR__, 3).'/resources/views']);

    foreach ($views as $view) {
        $source = $view->getContents();

        if (! str_contains($source, 'data-remote-modal=')) {
            continue;
        }

        preg_match_all('/(?:data-page|<x-[a-z]+::layout\b[^>]*\spage)="([a-z0-9-]+)"/', $source, $pages);

        foreach (array_unique($pages[1]) as $page) {
            $modules = $registry[$page] ?? [];
            $bound = array_filter($modules, static fn (string $module): bool => remoteModalModuleBinds($module));

            if ($modules !== [] && $bound === []) {
                $unbound[] = $view->getRelativePathname().' ['.$page.']';
            }
        }
    }

    expect($unbound)->toBe([]);
});
