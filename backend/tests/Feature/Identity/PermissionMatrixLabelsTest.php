<?php

use App\Modules\Identity\Support\PermissionMatrix;
use Illuminate\Support\Str;

/*
| RQA-ADM-002: the role matrices showed dotted resources ("catalog.categories") as English
| headline fallbacks in both locales because the translator read them as nested keys.
*/

it('translates every resource, module and ability of the role matrix', function (string $guard, string $locale): void {
    $matrix = PermissionMatrix::for($guard);
    $missing = [];

    foreach ($matrix['abilities'] as $ability) {
        PermissionMatrix::hasLabel('abilities', $ability, $locale) || $missing[] = 'ability '.$ability;
    }

    foreach ($matrix['modules'] as $module => $resources) {
        PermissionMatrix::hasLabel('modules', $module, $locale) || $missing[] = 'module '.$module;

        foreach (array_keys($resources) as $resource) {
            PermissionMatrix::hasLabel('resources', $resource, $locale) || $missing[] = 'resource '.$resource;
        }
    }

    expect($missing)->toBeEmpty()->and($matrix['modules'])->not->toHaveKey('other');
})->with(['admin', 'vendor'])->with(['en', 'ar']);

it('shows Arabic labels that differ from the English ones', function (string $guard): void {
    $matrix = PermissionMatrix::for($guard);
    $same = [];

    foreach ($matrix['modules'] as $module => $resources) {
        PermissionMatrix::moduleLabel($module, 'en') !== PermissionMatrix::moduleLabel($module, 'ar') || $same[] = 'module '.$module;

        foreach (array_keys($resources) as $resource) {
            $en = PermissionMatrix::resourceLabel($resource, 'en');
            $en !== PermissionMatrix::resourceLabel($resource, 'ar') || $same[] = 'resource '.$resource;
            $en !== Str::headline(str_replace('.', ' ', $resource)) || str_contains($resource, '.') === false || $same[] = 'headline '.$resource;
        }
    }

    foreach ($matrix['abilities'] as $ability) {
        PermissionMatrix::abilityLabel($ability, 'en') !== PermissionMatrix::abilityLabel($ability, 'ar') || $same[] = 'ability '.$ability;
    }

    expect($same)->toBeEmpty();
})->with(['admin', 'vendor']);

it('renders the dotted resource labels in the admin role editor', function (): void {
    expect(PermissionMatrix::resourceLabel('catalog.categories', 'en'))->toBe('Categories')
        ->and(PermissionMatrix::resourceLabel('shipping.fixed-costs', 'en'))->toBe('Fixed shipping costs')
        ->and(PermissionMatrix::resourceLabel('catalog.categories', 'ar'))->not->toBe('Categories');
});
