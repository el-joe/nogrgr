<?php

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Rules\ValidTenantSlug;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Tenant slugs address the central-host vendor panel (/tenant/{slug}) and the
| default sub-domain ({slug}.base-domain): valid DNS label, reserved words refused.
*/

function slugPasses(mixed $value): bool
{
    return validator(['slug' => $value], ['slug' => ['required', new ValidTenantSlug]])->passes();
}

/**
 * Reserved words read straight from the source files (datasets resolve before the app boots).
 *
 * @return list<string>
 */
function reservedSlugDataset(): array
{
    $config = require dirname(__DIR__, 3).'/config/tenancy.php';

    return array_values(array_unique([...TenantSlug::ROUTE_RESERVED, ...$config['reserved_subdomains']]));
}

it('accepts valid DNS labels', function (string $slug): void {
    expect(TenantSlug::isValid($slug))->toBeTrue()
        ->and(slugPasses($slug))->toBeTrue();
})->with(['acme', 'a', 'acme-store', 'shop24', '9lives', str_repeat('a', 63)]);

it('refuses malformed slugs', function (mixed $slug): void {
    expect(slugPasses($slug))->toBeFalse();

    if (is_string($slug)) {
        expect(TenantSlug::isValid($slug))->toBeFalse();
    }
})->with([
    'upper case' => 'Acme',
    'leading hyphen' => '-acme',
    'trailing hyphen' => 'acme-',
    'underscore' => 'acme_store',
    'dot' => 'acme.store',
    'space' => 'acme store',
    'unicode' => 'متجر',
    'empty' => '',
    'too long' => str_repeat('a', 64),
    'not a string' => 123,
    'null' => null,
]);

it('refuses every reserved word, case-insensitively', function (string $word): void {
    expect(TenantSlug::isReserved($word))->toBeTrue()
        ->and(TenantSlug::isReserved(strtoupper($word)))->toBeTrue()
        ->and(TenantSlug::isValid($word))->toBeFalse()
        ->and(slugPasses($word))->toBeFalse();
})->with(reservedSlugDataset());

it('reserves the central /tenant/* route words and the configured sub-domains', function (): void {
    expect(TenantSlug::reserved())
        ->toContain(...TenantSlug::ROUTE_RESERVED)
        ->toContain(...config('tenancy.reserved_subdomains'))
        ->toContain('www', 'admin', 'api', 'tenant', 'affiliate');
});

it('reserves every static segment of central /tenant/* routes so no slug can shadow them', function (): void {
    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => $route->uri())
        ->filter(fn (string $uri): bool => Str::startsWith($uri, 'tenant/'))
        ->map(fn (string $uri): string => explode('/', $uri)[1])
        ->reject(fn (string $segment): bool => Str::startsWith($segment, '{'))
        ->unique()
        ->values();

    expect($segments)->not->toBeEmpty();

    foreach ($segments as $segment) {
        expect(TenantSlug::isReserved($segment))->toBeTrue("Route segment [{$segment}] is not a reserved slug.");
    }
});

it('shows the translated validation message in both languages', function (string $locale): void {
    app()->setLocale($locale);

    $errors = validator(['slug' => 'admin'], ['slug' => [new ValidTenantSlug]])->errors();

    expect($errors->first('slug'))->toBe(__('platform::validation.tenant_slug'))
        ->not->toBe('platform::validation.tenant_slug');
})->with(['en', 'ar']);

it('builds the default store sub-domain host', function (): void {
    config(['tenancy.base_domain' => 'nogrgr.test']);

    expect(TenantSlug::subdomainHost('Acme'))->toBe('acme.nogrgr.test');
});

it('refuses to persist a tenant with a reserved or malformed slug', function (string $slug): void {
    Tenant::query()->create(['name' => 'Bad', 'slug' => $slug]);
})->with(['admin', 'login', 'Bad_Slug'])->throws(InvalidArgumentException::class);

it('refuses to rename an existing tenant to a reserved slug', function (): void {
    $tenant = $this->createTenant('acme');

    expect(fn () => $tenant->update(['slug' => 'stores']))->toThrow(InvalidArgumentException::class)
        ->and($tenant->fresh()->slug)->toBe('acme');
});
