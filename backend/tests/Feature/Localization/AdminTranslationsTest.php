<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Jobs\SyncTenantStaticTexts;
use App\Modules\Localization\Models\TranslationKeyLock;
use App\Modules\Localization\Models\TranslationOverride;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use Illuminate\Support\Facades\Queue;

/*
| Admin › Localization › Translations (ADM-092) and key locks (ADM-093): permissions,
| page + table (missing keys, locked tab), view / edit / add key / create file / revert
| / export, overrides applied by the translator, and locks blocking edits.
*/

function translationsAdmin(array $permissions = ['localization.translations.manage', 'localization.key-locks.manage']): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_merge(['dashboard.view'], $permissions)));

    return $admin;
}

function translationsUrl(string $path, array $query = []): string
{
    return test()->centralUrl('/admin/localization/'.ltrim($path, '/')).($query === [] ? '' : '?'.http_build_query($query));
}

dataset('translation endpoints', [
    'index' => ['get', 'translations', [], 'localization.translations.view'],
    'data' => ['get', 'translations/data', ['file' => 'storefront'], 'localization.translations.view'],
    'show' => ['get', 'translations/entry', ['file' => 'storefront', 'key' => 'shell.welcome'], 'localization.translations.view'],
    'export' => ['get', 'translations/export', ['file' => 'storefront'], 'localization.translations.view'],
    'edit' => ['get', 'translations/entry/edit', ['file' => 'storefront', 'key' => 'shell.welcome'], 'localization.translations.manage'],
    'update' => ['put', 'translations/entry', [], 'localization.translations.manage'],
    'revert' => ['delete', 'translations/entry', ['file' => 'storefront', 'key' => 'shell.welcome'], 'localization.translations.manage'],
    'create key' => ['get', 'translations/keys/create', ['file' => 'json'], 'localization.translations.manage'],
    'store key' => ['post', 'translations/keys', [], 'localization.translations.manage'],
    'create file' => ['get', 'translations/files/create', [], 'localization.translations.manage'],
    'store file' => ['post', 'translations/files', [], 'localization.translations.manage'],
    'locks index' => ['get', 'key-locks', [], 'localization.key-locks.view'],
    'lock' => ['patch', 'key-locks', ['file' => 'storefront', 'key' => 'shell.welcome', 'locked' => 1], 'localization.key-locks.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path, array $query): void {
    $this->{$method}(translationsUrl($path, $query))->assertRedirect($this->centralUrl('/admin/login'));
})->with('translation endpoints');

it('forbids admins without the permission', function (string $method, string $path, array $query, string $permission): void {
    $other = str_ends_with($permission, '.manage') ? [str_replace('.manage', '.view', $permission)] : ['localization.languages.view'];

    $this->actingAs(translationsAdmin($other), 'admin')
        ->{$method.'Json'}(translationsUrl($path, $query))
        ->assertForbidden();
})->with('translation endpoints');

it('renders the editor with file / language pickers, tabs and table', function (): void {
    $this->actingAs(translationsAdmin(), 'admin')
        ->get(translationsUrl('translations', ['file' => 'localization::translations', 'locale' => 'ar']))
        ->assertOk()
        ->assertSee(__('localization::translations.title'))
        ->assertSee('data-datatable', false)
        ->assertSee('data-page="admin-translations"', false)
        ->assertSee(__('localization::translations.tabs.locked'))
        ->assertSee(route('admin.localization.translations.files.create'), false);
});

it('hides manage actions from view-only admins and shows the locked tab on the locks page', function (): void {
    $this->actingAs(translationsAdmin(['localization.translations.view', 'localization.key-locks.view']), 'admin')
        ->get(translationsUrl('key-locks'))
        ->assertOk()
        ->assertSee(__('localization::translations.locks_title'))
        ->assertDontSee(route('admin.localization.translations.files.create'), false);
});

it('lists keys with the language value and missing keys per locale', function (): void {
    $admin = translationsAdmin();
    TranslationOverride::query()->create(['file' => 'json', 'key' => 'Only English here', 'locale' => 'en', 'value' => 'Only English here']);

    $response = $this->actingAs($admin, 'admin')
        ->getJson(translationsUrl('translations/data', ['file' => 'json', 'locale' => 'ar', 'tab' => 'missing', 'draw' => 1, 'start' => 0, 'length' => 100, 'search' => 'Only English']))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0.key'))->toContain('Only English here');

    $this->actingAs($admin, 'admin')
        ->getJson(translationsUrl('translations/data', ['file' => 'storefront', 'locale' => 'en', 'draw' => 1, 'start' => 0, 'length' => 10, 'search' => 'shell.footer.about']))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('data.0.source', fn (string $html): bool => str_contains($html, 'About us'));
});

it('saves an override that the translator applies and that survives the files', function (): void {
    $admin = translationsAdmin();

    $this->actingAs($admin, 'admin')
        ->putJson(translationsUrl('translations/entry'), ['file' => 'localization::translations', 'key' => 'export', 'values' => ['en' => 'Download JSON', 'ar' => '']])
        ->assertOk();

    expect(TranslationOverride::query()->where('file', 'localization::translations')->where('key', 'export')->where('locale', 'en')->value('value'))->toBe('Download JSON')
        ->and(TranslationOverride::query()->where('locale', 'ar')->count())->toBe(0)
        ->and(__('localization::translations.export', [], 'en'))->toBe('Download JSON')
        ->and(__('localization::translations.export', [], 'ar'))->toBe('تصدير JSON');

    $this->actingAs($admin, 'admin')
        ->get(translationsUrl('translations/entry', ['file' => 'localization::translations', 'key' => 'export']))
        ->assertOk()
        ->assertSee('Download JSON');

    $this->actingAs($admin, 'admin')
        ->deleteJson(translationsUrl('translations/entry', ['file' => 'localization::translations', 'key' => 'export']))
        ->assertOk();

    expect(TranslationOverride::query()->count())->toBe(0)
        ->and(__('localization::translations.export', [], 'en'))->toBe('Export JSON');
});

it('queues a store sync when the storefront base set is edited', function (): void {
    Queue::fake();
    $tenant = $this->createTenant('basesync');
    $tenant->forceFill(['provisioned_at' => now()])->save();

    $this->actingAs(translationsAdmin(), 'admin')
        ->putJson(translationsUrl('translations/entry'), ['file' => 'storefront', 'key' => 'shell.welcome', 'values' => ['en' => 'Hi and welcome']])
        ->assertOk();

    expect(__st('shell.welcome', [], 'en'))->toBe('Hi and welcome');
    Queue::assertPushed(SyncTenantStaticTexts::class, fn (SyncTenantStaticTexts $job): bool => $job->tenantId === $tenant->id);
});

it('adds a key and creates a file, refusing duplicates and bad keys', function (): void {
    $admin = translationsAdmin();

    $this->actingAs($admin, 'admin')->get(translationsUrl('translations/keys/create', ['file' => 'json']))->assertOk()->assertSee('translation-form', false);
    $this->actingAs($admin, 'admin')->get(translationsUrl('translations/files/create'))->assertOk()->assertSee('name="namespace"', false);

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/keys'), ['file' => 'json', 'key' => 'Brand new string', 'values' => ['en' => 'Brand new string', 'ar' => 'نص جديد']])
        ->assertOk();
    expect(__('Brand new string', [], 'ar'))->toBe('نص جديد');

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/keys'), ['file' => 'json', 'key' => 'Brand new string', 'values' => ['en' => 'Again']])
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/keys'), ['file' => 'ui', 'key' => 'bad key!', 'values' => ['en' => 'x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/files'), ['namespace' => 'localization', 'name' => 'reports', 'key' => 'title', 'values' => ['en' => 'Reports', 'ar' => 'التقارير']])
        ->assertCreated()
        ->assertJsonPath('redirect', route('admin.localization.translations.index', ['file' => 'localization::reports']));

    expect(__('localization::reports.title', [], 'ar'))->toBe('التقارير');

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/files'), ['namespace' => 'localization', 'name' => 'reports', 'key' => 'other', 'values' => ['en' => 'x']])
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->postJson(translationsUrl('translations/files'), ['namespace' => 'nope', 'name' => 'x', 'key' => 'k', 'values' => ['en' => 'x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('namespace');
});

it('locks and unlocks a key, and a locked key cannot be edited or reverted', function (): void {
    $admin = translationsAdmin();

    $this->actingAs($admin, 'admin')
        ->patchJson(translationsUrl('key-locks', ['file' => 'storefront', 'key' => 'shell.welcome']), ['locked' => 1])
        ->assertOk();
    expect(TranslationKeyLock::query()->where('file', 'storefront')->where('key', 'shell.welcome')->exists())->toBeTrue();
    app()->forgetInstance(TranslationKeyLockService::class);

    $this->actingAs($admin, 'admin')
        ->putJson(translationsUrl('translations/entry'), ['file' => 'storefront', 'key' => 'shell.welcome', 'values' => ['en' => 'Nope']])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('localization::translations.errors.key_locked', ['key' => 'shell.welcome']));

    $this->actingAs($admin, 'admin')
        ->getJson(translationsUrl('translations/data', ['file' => 'storefront', 'tab' => 'locked', 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);

    $this->actingAs($admin, 'admin')
        ->patchJson(translationsUrl('key-locks', ['file' => 'storefront', 'key' => 'shell.welcome']), ['locked' => 0])
        ->assertOk();
    expect(TranslationKeyLock::query()->count())->toBe(0);

    $this->actingAs($admin, 'admin')
        ->patchJson(translationsUrl('key-locks', ['file' => 'storefront', 'key' => 'shell.missing_key']), ['locked' => 1])
        ->assertNotFound();
});

it('exports a file in a language as JSON', function (): void {
    $response = $this->actingAs(translationsAdmin(), 'admin')
        ->get(translationsUrl('translations/export', ['file' => 'storefront', 'locale' => 'ar']))
        ->assertOk()
        ->assertDownload('storefront.ar.json');

    $json = json_decode($response->streamedContent(), true);

    expect($json)->toHaveKey('shell.welcome');
});

it('returns 404 for an unknown key in the view modal', function (): void {
    $this->actingAs(translationsAdmin(), 'admin')
        ->get(translationsUrl('translations/entry', ['file' => 'storefront', 'key' => 'shell.nope']))
        ->assertNotFound();
});
