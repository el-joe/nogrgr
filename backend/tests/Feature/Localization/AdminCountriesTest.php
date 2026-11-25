<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Enums\Continent;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;

/*
| Admin › Localization › Countries: list JSON + filters, edit, active switch, permissions.
*/

function countriesAdmin(?string $permission = 'localization.countries.manage'): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_filter(['dashboard.view', $permission])));

    return $admin;
}

dataset('country endpoints', [
    'index' => ['get', '/admin/localization/countries', 'localization.countries.view'],
    'data' => ['get', '/admin/localization/countries/data', 'localization.countries.view'],
    'edit' => ['get', '/admin/localization/countries/{id}/edit', 'localization.countries.manage'],
    'update' => ['put', '/admin/localization/countries/{id}', 'localization.countries.manage'],
    'toggle' => ['patch', '/admin/localization/countries/{id}/active', 'localization.countries.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $country = Country::factory()->create();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $country->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('country endpoints');

it('forbids admins without the country permission', function (string $method, string $path, string $permission): void {
    $country = Country::factory()->create();
    $other = $permission === 'localization.countries.manage' ? 'localization.countries.view' : null;

    $this->actingAs(countriesAdmin($other), 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $country->id, $path)))
        ->assertForbidden();
})->with('country endpoints');

it('renders the list page', function (): void {
    $this->actingAs(countriesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/countries'))
        ->assertOk()
        ->assertSee(__('localization::admin.countries.title'))
        ->assertSee('data-datatable', false);
});

it('serves the countries table with search, region and status filters', function (): void {
    Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU', 'name' => ['en' => 'Saudi Arabia', 'ar' => 'السعودية'], 'continent' => Continent::Asia, 'phone_code' => '966']);
    Country::factory()->create(['iso2' => 'FR', 'iso3' => 'FRA', 'name' => ['en' => 'France', 'ar' => 'فرنسا'], 'continent' => Continent::Europe, 'is_active' => false]);
    $admin = countriesAdmin();

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/countries/data?draw=1&start=0&length=10&search[value]=%2B966'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.iso2', 'SA');

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/countries/data?draw=1&start=0&length=10&continent=europe&status=inactive'))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.iso2', 'FR');
});

it('updates a country and keeps its ISO codes', function (): void {
    Currency::factory()->create(['code' => 'SAR']);
    $country = Country::factory()->create(['iso2' => 'SA', 'iso3' => 'SAU']);

    $this->actingAs(countriesAdmin(), 'admin')
        ->putJson($this->centralUrl("/admin/localization/countries/{$country->id}"), [
            'iso2' => 'ZZ',
            'name' => ['en' => 'Saudi Arabia', 'ar' => 'المملكة العربية السعودية'],
            'phone_code' => '+966',
            'currency_code' => 'SAR',
            'continent' => 'asia',
            'is_active' => '0',
        ])
        ->assertOk()
        ->assertJsonPath('redirect', route('admin.localization.countries.index'));

    expect($country->refresh()->iso2)->toBe('SA')
        ->and($country->phone_code)->toBe('966')
        ->and($country->currency_code)->toBe('SAR')
        ->and($country->continent)->toBe(Continent::Asia)
        ->and($country->is_active)->toBeFalse()
        ->and($country->getTranslation('name', 'ar'))->toBe('المملكة العربية السعودية');
});

it('validates country input', function (): void {
    $country = Country::factory()->create();

    $this->actingAs(countriesAdmin(), 'admin')
        ->putJson($this->centralUrl("/admin/localization/countries/{$country->id}"), [
            'name' => ['en' => ''],
            'phone_code' => 'abc',
            'currency_code' => 'QQQ',
            'continent' => 'atlantis',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.en', 'name.ar', 'phone_code', 'currency_code', 'continent', 'is_active']);
});

it('toggles a country', function (): void {
    $country = Country::factory()->create(['is_active' => true]);

    $this->actingAs(countriesAdmin(), 'admin')
        ->patchJson($this->centralUrl("/admin/localization/countries/{$country->id}/active"), ['is_active' => 0])
        ->assertOk();

    expect($country->refresh()->is_active)->toBeFalse();
});

it('renders the edit form', function (): void {
    $country = Country::factory()->create(['name' => ['en' => 'Egypt', 'ar' => 'مصر']]);

    $this->actingAs(countriesAdmin(), 'admin')
        ->get($this->centralUrl("/admin/localization/countries/{$country->id}/edit"))
        ->assertOk()
        ->assertSee('Egypt');
});
