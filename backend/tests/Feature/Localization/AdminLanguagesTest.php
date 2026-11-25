<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Database\Seeders\LanguageSeeder;
use App\Modules\Localization\Enums\TextDirection;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Language;

/*
| Admin › Localization › Languages: ISO seed, list JSON + filters, create / edit,
| active switch, delete, permissions and the ar / en locks.
*/

function languagesAdmin(?string $permission = 'localization.languages.manage'): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_filter(['dashboard.view', $permission])));

    return $admin;
}

function languagePayload(array $overrides = []): array
{
    return array_replace([
        'code' => 'xx',
        'name' => ['en' => 'Testish', 'ar' => 'تستية'],
        'native_name' => 'Testish',
        'direction' => 'ltr',
        'flag' => 'fr',
        'is_active' => '1',
        'price' => '49.00',
        'sort_order' => '5',
    ], $overrides);
}

it('seeds every ISO 639-1 language with rtl scripts and ar / en on and free', function (): void {
    $this->seed(LanguageSeeder::class);

    $arabic = Language::query()->where('code', 'ar')->sole();
    $english = Language::query()->where('code', 'en')->sole();
    $rtl = Language::query()->where('direction', TextDirection::Rtl)->orderBy('code')->pluck('code')->all();

    expect(Language::query()->count())->toBe(184)
        ->and($rtl)->toBe(['ar', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi'])
        ->and($arabic->is_active && $arabic->price_minor === 0)->toBeTrue()
        ->and($english->is_active && $english->is_default)->toBeTrue()
        ->and(Language::query()->where('code', 'fr')->value('is_active'))->toBeFalse();

    Language::query()->where('code', 'fr')->update(['is_active' => true, 'price_minor' => 4900]);
    $this->seed(LanguageSeeder::class);

    expect(Language::query()->count())->toBe(184)
        ->and(Language::query()->where('code', 'fr')->value('price_minor'))->toBe(4900)
        ->and(Language::query()->where('code', 'fr')->value('is_active'))->toBeTrue();
});

dataset('language endpoints', [
    'index' => ['get', '/admin/localization/languages', 'localization.languages.view'],
    'data' => ['get', '/admin/localization/languages/data', 'localization.languages.view'],
    'create' => ['get', '/admin/localization/languages/create', 'localization.languages.manage'],
    'store' => ['post', '/admin/localization/languages', 'localization.languages.manage'],
    'edit' => ['get', '/admin/localization/languages/{id}/edit', 'localization.languages.manage'],
    'update' => ['put', '/admin/localization/languages/{id}', 'localization.languages.manage'],
    'toggle' => ['patch', '/admin/localization/languages/{id}/active', 'localization.languages.manage'],
    'destroy' => ['delete', '/admin/localization/languages/{id}', 'localization.languages.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $language = Language::factory()->create();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $language->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('language endpoints');

it('forbids admins without the language permission', function (string $method, string $path, string $permission): void {
    $language = Language::factory()->create();
    $other = $permission === 'localization.languages.manage' ? 'localization.languages.view' : null;

    $this->actingAs(languagesAdmin($other), 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $language->id, $path)))
        ->assertForbidden();
})->with('language endpoints');

it('renders the list page with tabs, table and create button', function (): void {
    Language::factory()->count(2)->create();

    $this->actingAs(languagesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/languages'))
        ->assertOk()
        ->assertSee(__('localization::admin.languages.title'))
        ->assertSee('data-datatable', false)
        ->assertSee(route('admin.localization.languages.create'), false)
        ->assertSee(__('localization::admin.languages.tab_all'));
});

it('hides manage actions from view-only admins', function (): void {
    $this->actingAs(languagesAdmin('localization.languages.view'), 'admin')
        ->get($this->centralUrl('/admin/localization/languages'))
        ->assertOk()
        ->assertDontSee(route('admin.localization.languages.create'), false);
});

it('serves the languages table with search and filters', function (): void {
    Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'price_minor' => 4900]);
    Language::factory()->create(['code' => 'ur', 'name' => ['en' => 'Urdu', 'ar' => 'الأردية'], 'direction' => TextDirection::Rtl, 'is_active' => false]);
    $admin = languagesAdmin();

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/languages/data?draw=1&start=0&length=10&search[value]=fren'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'fr')
        ->assertJsonPath('data.0.price', fn (string $html) => str_contains($html, '$49.00'));

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/languages/data?draw=1&start=0&length=10&direction=rtl&status=inactive'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'ur');

});

it('creates a language with its one USD price in minor units', function (): void {
    $country = Country::factory()->create();

    $this->actingAs(languagesAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/localization/languages'), languagePayload(['country_ids' => [$country->id]]))
        ->assertCreated()
        ->assertJsonPath('redirect', route('admin.localization.languages.index', ['tab' => 'all']));

    $language = Language::query()->where('code', 'xx')->sole();

    expect($language->price_minor)->toBe(4900)
        ->and($language->isPurchasable())->toBeTrue()
        ->and($language->flag)->toBe('FR')
        ->and($language->country_ids)->toBe([$country->id])
        ->and($language->getTranslation('name', 'ar'))->toBe('تستية');
});

it('validates language input', function (): void {
    Language::factory()->create(['code' => 'xx']);

    $this->actingAs(languagesAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/localization/languages'), languagePayload([
            'code' => 'xx',
            'name' => ['en' => ''],
            'direction' => 'up',
            'price' => '0',
            'country_ids' => [999999],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name.en', 'name.ar', 'direction', 'price', 'country_ids.0']);
});

it('updates a language without changing its code', function (): void {
    $language = Language::factory()->create(['code' => 'fr', 'price_minor' => 4900]);

    $this->actingAs(languagesAdmin(), 'admin')
        ->putJson($this->centralUrl("/admin/localization/languages/{$language->id}"), languagePayload(['price' => '39.5', 'code' => null, 'is_active' => '0']))
        ->assertOk();

    expect($language->refresh()->code)->toBe('fr')
        ->and($language->price_minor)->toBe(3950)
        ->and($language->is_active)->toBeFalse();

    $this->actingAs(languagesAdmin(), 'admin')
        ->putJson($this->centralUrl("/admin/localization/languages/{$language->id}"), languagePayload(['code' => 'de']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('toggles a language and deletes it', function (): void {
    $language = Language::factory()->create(['code' => 'fr', 'is_active' => false]);
    $admin = languagesAdmin();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/localization/languages/{$language->id}/active"), ['is_active' => 1])
        ->assertOk();
    expect($language->refresh()->is_active)->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/localization/languages/{$language->id}/active"), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_active']);

    $this->actingAs($admin, 'admin')
        ->deleteJson($this->centralUrl("/admin/localization/languages/{$language->id}"))
        ->assertOk();
    expect(Language::query()->whereKey($language->id)->exists())->toBeFalse();
});

it('locks arabic and english', function (string $code): void {
    $language = Language::factory()->create(['code' => $code, 'is_active' => true, 'price_minor' => 0]);
    $admin = languagesAdmin();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/localization/languages/{$language->id}/active"), ['is_active' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('localization::admin.languages.locked'));

    $this->actingAs($admin, 'admin')
        ->deleteJson($this->centralUrl("/admin/localization/languages/{$language->id}"))
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/languages/{$language->id}"), languagePayload(['code' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/languages/{$language->id}"), languagePayload(['code' => null, 'price' => null, 'is_active' => '0']))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('localization::admin.languages.locked'));

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/languages/{$language->id}"), languagePayload(['code' => null, 'price' => null, 'sort_order' => '9']))
        ->assertOk();

    expect($language->refresh()->is_active)->toBeTrue()
        ->and($language->price_minor)->toBe(0)
        ->and($language->sort_order)->toBe(9)
        ->and(Language::query()->whereKey($language->id)->exists())->toBeTrue();
})->with(['ar', 'en']);

it('renders the create and edit forms', function (): void {
    $language = Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية']]);
    $admin = languagesAdmin();

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/localization/languages/create'))
        ->assertOk()
        ->assertSee('name="code"', false);

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl("/admin/localization/languages/{$language->id}/edit"))
        ->assertOk()
        ->assertSee('French')
        ->assertDontSee('name="code"', false);
});
