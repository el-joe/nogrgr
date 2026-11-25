<?php

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Plan;
use App\Modules\Localization\Enums\StaticTextSource;
use App\Modules\Localization\Events\StaticTextsNeedTranslation;
use App\Modules\Localization\Exceptions\TranslationRuleException;
use App\Modules\Localization\Jobs\SyncTenantStaticTexts;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Models\TenantStaticText;
use App\Modules\Localization\Models\TranslationKeyLock;
use App\Modules\Localization\Repositories\Contracts\TranslationOverrideRepositoryContract;
use App\Modules\Localization\Services\StaticTexts\StaticTextCatalog;
use App\Modules\Localization\Services\StaticTexts\StaticTextResolver;
use App\Modules\Localization\Services\StaticTexts\StaticTextSyncService;
use App\Modules\Localization\Services\StaticTexts\TenantStaticTextService;
use App\Modules\Localization\Services\Translations\TranslationKeyLockService;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Platform/Vendors/VendorHelpers.php';

/*
| Storefront static texts per store (decision #45): provisioning copy, deploy sync,
| cached resolver with fallback, the vendor service API and platform locks.
*/

/**
 * A base set in a temp directory (unique per test, parallel safe).
 *
 * @param  array<string, array<string, mixed>>  $groups  locale => [group => lines]
 */
function fakeStaticTextCatalog(array $groups): StaticTextCatalog
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nogrgr-static-'.uniqid('', true);

    foreach ($groups as $locale => $files) {
        File::ensureDirectoryExists($path.DIRECTORY_SEPARATOR.$locale);

        foreach ($files as $group => $lines) {
            File::put($path.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group.'.php', '<?php return '.var_export($lines, true).';');
        }
    }

    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($path));
    $catalog = new StaticTextCatalog(app(TranslationOverrideRepositoryContract::class), $path);
    app()->instance(StaticTextCatalog::class, $catalog);
    app()->forgetInstance(StaticTextResolver::class);
    app()->forgetInstance(StaticTextSyncService::class);
    app()->forgetInstance(TenantStaticTextService::class);

    return $catalog;
}

it('copies the storefront base texts into a new store at provisioning', function (): void {
    cleanUpProvisionedTenants();
    Event::fake([TenantProvisioned::class]);
    $plan = Plan::factory()->create(['trial_days' => 14]);

    $tenant = app(TenantProvisioningService::class)->provision(ProvisionTenantData::fromArray([
        'store_name' => 'Static Shop',
        'slug' => 'staticshop',
        'owner_name' => 'Static Owner',
        'owner_email' => 'owner@staticshop.test',
        'owner_phone' => '+966552201188',
        'owner_password' => 'secret-pass-123',
        'plan_id' => $plan->id,
        'term' => BillingTerm::Monthly,
        'plan_assignment' => PlanAssignment::Trial,
    ]));

    $catalog = app(StaticTextCatalog::class);

    $tenant->refresh()->run(function () use ($catalog): void {
        $en = TenantStaticText::query()->where('locale', 'en')->pluck('value', 'key')->all();
        $ar = TenantStaticText::query()->where('locale', 'ar')->pluck('value', 'key')->all();

        expect(count($en))->toBe(count($catalog->lines('en')))
            ->and($en['shell.footer.about'])->toBe('About us')
            ->and($ar['shell.footer.about'])->toBe($catalog->value('shell.footer.about', 'ar'))
            ->and(TenantStaticText::query()->where('source', '!=', StaticTextSource::Base->value)->count())->toBe(0)
            ->and(TenantStaticText::query()->where('key', 'shell.welcome')->where('locale', 'en')->value('source_hash'))->toBe(sha1('Welcome'));
    });
});

it('syncs new base keys and refreshes untouched rows without overwriting edited or locked ones', function (): void {
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hello', 'bye' => 'Bye', 'cart' => 'Cart']], 'ar' => ['shell' => ['hello' => 'مرحبا', 'bye' => 'وداعا', 'cart' => 'السلة']]]);
    $tenant = $this->createTenant('syncer');
    $this->initializeTenancy($tenant);
    app(StaticTextSyncService::class)->syncCurrentTenant();

    app(TenantStaticTextService::class)->update('shell.bye', 'en', 'See you');
    TenantStaticText::query()->where('key', 'shell.cart')->where('locale', 'en')->update(['is_locked' => true]);

    // Deploy: one new key, every English source changed.
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hi there', 'bye' => 'Goodbye', 'cart' => 'Basket', 'new_in' => 'New in']], 'ar' => ['shell' => ['hello' => 'مرحبا', 'bye' => 'وداعا', 'cart' => 'السلة', 'new_in' => 'وصل حديثا']]]);
    $result = app(StaticTextSyncService::class)->syncCurrentTenant();
    $en = TenantStaticText::query()->where('locale', 'en')->pluck('value', 'key')->all();

    expect($result->newKeys)->toBe(['shell.new_in'])
        ->and($result->inserted)->toBe(2)
        ->and($result->updated)->toBe(1)
        ->and($en)->toMatchArray(['shell.hello' => 'Hi there', 'shell.bye' => 'See you', 'shell.cart' => 'Cart', 'shell.new_in' => 'New in'])
        ->and(TenantStaticText::query()->where('key', 'shell.bye')->where('locale', 'en')->value('source'))->toBe(StaticTextSource::Manual)
        ->and($result->translationLocales)->toBe([]);
});

it('asks for an AI translation of new keys when the store bought languages', function (): void {
    Event::fake([StaticTextsNeedTranslation::class]);
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hello']], 'ar' => ['shell' => ['hello' => 'مرحبا']]]);
    $tenant = $this->createTenant('buyerlang');
    $this->initializeTenancy($tenant);
    TenantLanguage::factory()->create(['language_code' => 'fr', 'is_enabled' => true]);

    app(StaticTextSyncService::class)->syncCurrentTenant();

    Event::assertDispatched(StaticTextsNeedTranslation::class, fn (StaticTextsNeedTranslation $event): bool => $event->keys === ['shell.hello'] && $event->locales === ['fr'] && (string) $event->tenantId === (string) $tenant->id);
});

it('queues the deploy sync for every provisioned store', function (): void {
    Queue::fake();
    $live = $this->createTenant('livestore');
    $live->forceFill(['provisioned_at' => now()])->save();
    $this->createTenant('pendingstore');

    $this->artisan('localization:sync-static-texts')->assertSuccessful();

    Queue::assertPushed(SyncTenantStaticTexts::class, 1);
    Queue::assertPushed(SyncTenantStaticTexts::class, fn (SyncTenantStaticTexts $job): bool => $job->tenantId === $live->id);
});

it('runs the sync job inside the store', function (): void {
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hello']], 'ar' => ['shell' => ['hello' => 'مرحبا']]]);
    $tenant = $this->createTenant('jobstore');
    $tenant->forceFill(['provisioned_at' => now()])->save();

    SyncTenantStaticTexts::dispatchSync($tenant->id);

    $tenant->run(fn () => expect(TenantStaticText::query()->count())->toBe(2));
});

it('resolves store values, falls back to the base set and English, and caches per store and locale', function (): void {
    fakeStaticTextCatalog([
        'en' => ['shell' => ['hello' => 'Hello :name', 'only_en' => 'English only', 'base' => 'Base']],
        'ar' => ['shell' => ['hello' => 'مرحبا :name', 'base' => 'أساس']],
    ]);
    $tenant = $this->createTenant('resolver');
    $this->initializeTenancy($tenant);
    app(StaticTextSyncService::class)->syncCurrentTenant();
    TenantStaticText::query()->where('key', 'shell.base')->where('locale', 'ar')->delete();
    app(StaticTextResolver::class)->forget();

    $resolver = app(StaticTextResolver::class);

    expect($resolver->get('shell.hello', ['name' => 'Sara'], 'ar'))->toBe('مرحبا Sara')
        ->and($resolver->get('shell.only_en', [], 'ar'))->toBe('English only')
        ->and($resolver->get('shell.base', [], 'ar'))->toBe('أساس')
        ->and($resolver->get('shell.unknown', [], 'ar'))->toBe('shell.unknown')
        ->and($resolver->get('shell.hello', ['name' => 'x'], 'fr'))->toBe('Hello x')
        ->and(app('cache')->store()->has($resolver->cacheKey((string) $tenant->id, 'ar')))->toBeTrue();

    // Cached: a direct DB change is not seen until a service write busts the cache.
    DB::connection((new TenantStaticText)->getConnectionName())->table('tenant_static_texts')->where('key', 'shell.hello')->where('locale', 'ar')->update(['value' => 'أهلا']);
    app()->forgetInstance(StaticTextResolver::class);
    expect(__st('shell.hello', ['name' => 'A'], 'ar'))->toBe('مرحبا A');

    app(TenantStaticTextService::class)->update('shell.hello', 'ar', 'هلا :name');
    expect(__st('shell.hello', ['name' => 'A'], 'ar'))->toBe('هلا A')
        ->and(__st('identity::account.nav.profile'))->toBe(__('identity::account.nav.profile'));
});

it('lets a store edit, lock, unlock and reset its texts', function (): void {
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hello']], 'ar' => ['shell' => ['hello' => 'مرحبا']]]);
    $tenant = $this->createTenant('editor');
    $this->initializeTenancy($tenant);
    app(StaticTextSyncService::class)->syncCurrentTenant();
    $service = app(TenantStaticTextService::class);

    $row = $service->update('shell.hello', 'en', 'Welcome!');
    expect($row->is_locked)->toBeTrue()->and($row->source)->toBe(StaticTextSource::Manual);

    $row = $service->unlock('shell.hello', 'en');
    expect($row->is_locked)->toBeFalse()->and($row->source)->toBe(StaticTextSource::Base);

    $service->lock('shell.hello', 'en');
    $row = $service->reset('shell.hello', 'en');
    expect($row?->value)->toBe('Hello')->and($row?->is_locked)->toBeFalse()
        ->and($service->list('en', 'hello')->pluck('key')->all())->toBe(['shell.hello']);

    expect(fn () => $service->update('shell.nope', 'en', 'x'))->toThrow(TranslationRuleException::class);
});

it('blocks store edits of keys the platform locked and shows the base value for them', function (): void {
    fakeStaticTextCatalog(['en' => ['shell' => ['hello' => 'Hello']], 'ar' => ['shell' => ['hello' => 'مرحبا']]]);
    $tenant = $this->createTenant('lockedkeys');
    $this->initializeTenancy($tenant);
    app(StaticTextSyncService::class)->syncCurrentTenant();
    app(TenantStaticTextService::class)->update('shell.hello', 'en', 'Yo');

    TranslationKeyLock::query()->create(['file' => StaticTextCatalog::FILE, 'key' => 'shell.hello']);
    app()->forgetInstance(TranslationKeyLockService::class);
    app()->forgetInstance(StaticTextResolver::class);
    app()->forgetInstance(TenantStaticTextService::class);

    expect(fn () => app(TenantStaticTextService::class)->update('shell.hello', 'en', 'Hey'))->toThrow(TranslationRuleException::class)
        ->and(__st('shell.hello', [], 'en'))->toBe('Hello');
});

it('renders storefront pages from the store texts', function (): void {
    $tenant = $this->createTenant('rendered');
    $this->initializeTenancy($tenant);
    app(StaticTextSyncService::class)->syncCurrentTenant();
    app(TenantStaticTextService::class)->update('account.welcome_back', 'en', 'Log into Rendered');
    $this->endTenancy();

    $this->get($this->tenantUrl($tenant, '/account/login'))
        ->assertOk()
        ->assertSee('Log into Rendered');
});
