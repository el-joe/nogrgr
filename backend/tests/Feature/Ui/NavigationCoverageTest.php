<?php

use App\Shared\Navigation\NavigationBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;

/*
| Every single sidebar entry of every panel config is checked on its own: it is
| rendered if and only if its route is registered, its label is translated in
| en and ar, and its icon exists. No dead links, no raw translation keys.
*/

function permissiveNavigationUser(): User
{
    return new class extends User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    };
}

/**
 * Leaf items of a navigation config as [section, parent|null, leaf] triples.
 *
 * @return list<array{0: array<string, mixed>, 1: array<string, mixed>|null, 2: array<string, mixed>}>
 */
function navigationLeaves(array $config): array
{
    $leaves = [];

    foreach ($config as $section) {
        foreach ($section['items'] as $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    $leaves[] = [$section, $item, $child];
                }
            } else {
                $leaves[] = [$section, null, $item];
            }
        }
    }

    return $leaves;
}

/**
 * Build a config that contains only $leaf (inside its group when it has one).
 */
function isolatedNavigation(array $section, ?array $parent, array $leaf): array
{
    $item = $parent === null ? $leaf : [...$parent, 'children' => [$leaf]];

    return [[...$section, 'items' => [$item]]];
}

it('renders each sidebar item only when its route is registered', function (string $surface): void {
    $builder = app(NavigationBuilder::class);
    $leaves = navigationLeaves(config("navigation.{$surface}"));

    expect($leaves)->not->toBeEmpty();

    foreach ($leaves as [$section, $parent, $leaf]) {
        $built = $builder->build(isolatedNavigation($section, $parent, $leaf), permissiveNavigationUser());
        $registered = Route::has($leaf['route']);

        expect($built !== [])->toBe($registered, "{$surface} item [{$leaf['key']}] → route [{$leaf['route']}] registered=".var_export($registered, true));
    }
})->with(['admin', 'affiliate', 'vendor']);

it('shows a hidden item as soon as its route is registered', function (): void {
    $leaf = collect(navigationLeaves(config('navigation.admin')))
        ->first(fn (array $triple): bool => ! Route::has($triple[2]['route']));

    expect($leaf)->not->toBeNull();

    [$section, $parent, $item] = $leaf;
    $config = isolatedNavigation($section, $parent, $item);
    $builder = app(NavigationBuilder::class);

    expect($builder->build($config, permissiveNavigationUser()))->toBe([]);

    Route::get('/qa-nav-probe', fn () => 'ok')->name($item['route']);
    Route::getRoutes()->refreshNameLookups();

    $built = $builder->build($config, permissiveNavigationUser());
    $rendered = $parent === null ? $built[0]['items'][0] : $built[0]['items'][0]['children'][0];

    expect($rendered['key'])->toBe($item['key'])
        ->and($rendered['url'])->toEndWith('/qa-nav-probe');
});

it('declares a route, a permission and translated labels for every item', function (string $surface, string $locale): void {
    $missing = [];

    foreach (config("navigation.{$surface}") as $section) {
        $labels = array_filter([$section['label'] ?? null]);

        foreach ($section['items'] as $item) {
            $labels[] = $item['label'];

            foreach ($item['children'] ?? [] as $child) {
                $labels[] = $child['label'];
            }
        }

        foreach ($labels as $label) {
            if (__($label, [], $locale) === $label) {
                $missing[] = "{$locale}: {$label}";
            }
        }
    }

    foreach (navigationLeaves(config("navigation.{$surface}")) as [, , $leaf]) {
        if (empty($leaf['route']) || empty($leaf['permission'])) {
            $missing[] = "route/permission: {$leaf['key']}";
        }
    }

    expect($missing)->toBeEmpty();
})->with(['admin', 'affiliate', 'vendor'])->with(['en', 'ar']);

it('uses only icons that exist in the icon set', function (string $surface): void {
    $missing = [];

    foreach (config("navigation.{$surface}") as $section) {
        foreach ($section['items'] as $item) {
            if (isset($item['icon']) && ! is_file(resource_path("svg/icons/{$item['icon']}.svg"))) {
                $missing[] = $item['icon'];
            }
        }
    }

    expect($missing)->toBeEmpty();
})->with(['admin', 'affiliate', 'vendor']);

it('gives every navigation key a unique value per surface', function (string $surface): void {
    $keys = collect(config("navigation.{$surface}"))
        ->flatMap(fn (array $section) => collect($section['items'])->flatMap(fn (array $item) => [$item['key'], ...array_column($item['children'] ?? [], 'key')]));

    expect($keys->duplicates()->all())->toBe([]);
})->with(['admin', 'affiliate', 'vendor']);
