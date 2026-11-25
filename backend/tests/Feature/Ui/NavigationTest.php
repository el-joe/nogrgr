<?php

use App\Shared\Navigation\NavigationBuilder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;

/*
| The sidebar never renders a dead link: unregistered routes and items without
| permission disappear, then empty groups and sections.
*/

function navigationUser(bool $allowed): User
{
    return new class($allowed) extends User
    {
        public function __construct(private readonly bool $allowed = true)
        {
            parent::__construct();
        }

        public function can($abilities, $arguments = []): bool
        {
            return $this->allowed;
        }
    };
}

it('drops items whose route is not registered and keeps groups with reachable children', function (): void {
    Route::get('/nav-test/a', fn () => 'a')->name('nav-test.a.index');
    Route::getRoutes()->refreshNameLookups();

    $sections = app(NavigationBuilder::class)->build([
        ['key' => 's1', 'label' => 'navigation.sections.catalog', 'items' => [
            ['key' => 'exists', 'label' => 'navigation.admin.products', 'route' => 'nav-test.a.index', 'permission' => 'catalog.products.view'],
            ['key' => 'missing', 'label' => 'navigation.admin.categories', 'route' => 'nav-test.missing'],
            ['key' => 'group', 'label' => 'navigation.admin.labels_badges', 'children' => [
                ['key' => 'child-missing', 'label' => 'navigation.admin.product_labels', 'route' => 'nav-test.nope'],
            ]],
        ]],
        ['key' => 's2', 'label' => 'navigation.sections.sales', 'items' => [
            ['key' => 'orders', 'label' => 'navigation.admin.orders', 'route' => 'nav-test.orders.index'],
        ]],
    ], navigationUser(true));

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['items'])->toHaveCount(1)
        ->and($sections[0]['items'][0]['key'])->toBe('exists')
        ->and($sections[0]['items'][0]['url'])->toEndWith('/nav-test/a');
});

it('hides items the user lacks permission for', function (): void {
    Route::get('/nav-test/b', fn () => 'b')->name('nav-test.b.index');
    Route::getRoutes()->refreshNameLookups();

    $config = [['key' => 's', 'label' => null, 'items' => [
        ['key' => 'b', 'label' => 'navigation.admin.orders', 'route' => 'nav-test.b.index', 'permission' => 'orders.view'],
    ]]];

    expect(app(NavigationBuilder::class)->build($config, navigationUser(false)))->toBe([])
        ->and(app(NavigationBuilder::class)->build($config, null))->toBe([]);
});

it('ships an admin sidebar config that covers the IA and only links registered routes', function (): void {
    $config = config('navigation.admin');
    $keys = collect($config)->flatMap(fn (array $section) => collect($section['items'])->pluck('key'))->all();

    expect($keys)->toContain('dashboard', 'products', 'requests', 'orders', 'shipping', 'vendors', 'finance', 'affiliates', 'storefront', 'website', 'localization', 'settings', 'audit', 'system-tools');

    $sections = app(NavigationBuilder::class)->build($config, navigationUser(true));
    $urls = collect($sections)->flatMap(fn (array $section) => collect($section['items'])->flatMap(fn (array $item) => array_filter([$item['url'], ...array_column($item['children'], 'url')])))->all();

    expect($urls)->not->toBeEmpty()->each->toStartWith('http');
});
