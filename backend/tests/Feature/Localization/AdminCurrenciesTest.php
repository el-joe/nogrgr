<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Database\Seeders\CurrencySeeder;
use App\Modules\Localization\Jobs\SyncExchangeRates;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\CurrencyService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| Admin › Localization › Currencies: seed, list JSON, CRUD, active switch, the USD
| lock, "Sync rates now" and the SyncExchangeRates job (open.er-api.com, faked).
*/

function currenciesAdmin(?string $permission = 'localization.currencies.manage'): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_filter(['dashboard.view', $permission])));

    return $admin;
}

function usd(): Currency
{
    return Currency::factory()->create(['code' => 'USD', 'name' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'], 'symbol' => '$', 'is_base' => true, 'rate' => '1.000000']);
}

function currencyPayload(array $overrides = []): array
{
    return array_replace([
        'code' => 'xts',
        'name' => ['en' => 'Test Coin', 'ar' => 'عملة تجربة'],
        'symbol' => 'T',
        'decimals' => '2',
        'rate' => '3.75',
        'is_active' => '1',
        'sort_order' => '3',
    ], $overrides);
}

it('seeds ISO currencies with USD as the active base', function (): void {
    $this->seed(CurrencySeeder::class);

    $usd = Currency::query()->where('code', 'USD')->sole();

    expect(Currency::query()->count())->toBe(155)
        ->and($usd->is_base && $usd->is_active)->toBeTrue()
        ->and((float) $usd->rate)->toBe(1.0)
        ->and(Currency::query()->where('code', 'SAR')->value('is_active'))->toBeTrue()
        ->and(Currency::query()->where('code', 'JPY')->value('is_active'))->toBeFalse();
});

dataset('currency endpoints', [
    'index' => ['get', '/admin/localization/currencies', 'localization.currencies.view'],
    'data' => ['get', '/admin/localization/currencies/data', 'localization.currencies.view'],
    'create' => ['get', '/admin/localization/currencies/create', 'localization.currencies.manage'],
    'store' => ['post', '/admin/localization/currencies', 'localization.currencies.manage'],
    'sync' => ['post', '/admin/localization/currencies/sync', 'localization.currencies.manage'],
    'edit' => ['get', '/admin/localization/currencies/{id}/edit', 'localization.currencies.manage'],
    'update' => ['put', '/admin/localization/currencies/{id}', 'localization.currencies.manage'],
    'toggle' => ['patch', '/admin/localization/currencies/{id}/active', 'localization.currencies.manage'],
    'destroy' => ['delete', '/admin/localization/currencies/{id}', 'localization.currencies.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $currency = Currency::factory()->create();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $currency->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('currency endpoints');

it('forbids admins without the currency permission', function (string $method, string $path, string $permission): void {
    $currency = Currency::factory()->create();
    $other = $permission === 'localization.currencies.manage' ? 'localization.currencies.view' : null;

    $this->actingAs(currenciesAdmin($other), 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $currency->id, $path)))
        ->assertForbidden();
})->with('currency endpoints');

it('renders the list page with the sync button and last-sync banner', function (): void {
    usd()->update(['rate_synced_at' => now()]);

    $this->actingAs(currenciesAdmin(), 'admin')
        ->get($this->centralUrl('/admin/localization/currencies'))
        ->assertOk()
        ->assertSee(__('localization::admin.currencies.sync'))
        ->assertSee(route('admin.localization.currencies.sync'), false)
        ->assertSee('data-datatable', false);
});

it('serves the currencies table with search, status filter and country counts', function (): void {
    usd();
    Currency::factory()->create(['code' => 'SAR', 'name' => ['en' => 'Saudi Riyal', 'ar' => 'ريال سعودي'], 'rate' => '3.750000']);
    Currency::factory()->create(['code' => 'JPY', 'is_active' => false]);
    Country::factory()->create(['currency_code' => 'SAR']);
    $admin = currenciesAdmin();

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/currencies/data?draw=1&start=0&length=10&search[value]=riyal'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'SAR')
        ->assertJsonPath('data.0.rate', fn (string $html) => str_contains($html, '3.7500'));

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/localization/currencies/data?draw=1&start=0&length=10&status=inactive'))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'JPY');
});

it('creates, updates and toggles a currency', function (): void {
    $admin = currenciesAdmin();

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/localization/currencies'), currencyPayload())
        ->assertCreated();

    $currency = Currency::query()->where('code', 'XTS')->sole();
    expect((float) $currency->rate)->toBe(3.75)->and($currency->is_base)->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/currencies/{$currency->id}"), currencyPayload(['code' => null, 'rate' => '', 'symbol' => 'TC', 'decimals' => '3']))
        ->assertOk();
    expect($currency->refresh()->symbol)->toBe('TC')->and($currency->decimals)->toBe(3)->and((float) $currency->rate)->toBe(3.75);

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/localization/currencies/{$currency->id}/active"), ['is_active' => 0])
        ->assertOk();
    expect($currency->refresh()->is_active)->toBeFalse();
});

it('validates currency input', function (): void {
    usd();

    $this->actingAs(currenciesAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/localization/currencies'), currencyPayload(['code' => 'USD', 'symbol' => '', 'decimals' => '9', 'rate' => '-1']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'symbol', 'decimals', 'rate']);
});

it('locks the USD base currency', function (): void {
    $usd = usd();
    $admin = currenciesAdmin();

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/localization/currencies/{$usd->id}/active"), ['is_active' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('localization::admin.currencies.locked'));

    $this->actingAs($admin, 'admin')
        ->deleteJson($this->centralUrl("/admin/localization/currencies/{$usd->id}"))
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/currencies/{$usd->id}"), currencyPayload(['code' => null, 'rate' => '5', 'is_active' => '0']))
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/localization/currencies/{$usd->id}"), currencyPayload(['code' => null, 'rate' => '5', 'symbol' => 'US$']))
        ->assertOk();

    expect($usd->refresh()->is_active)->toBeTrue()
        ->and((float) $usd->rate)->toBe(1.0)
        ->and($usd->symbol)->toBe('US$');
});

it('deletes a currency unless a country uses it', function (): void {
    $used = Currency::factory()->create(['code' => 'SAR']);
    $free = Currency::factory()->create(['code' => 'XTS']);
    Country::factory()->create(['currency_code' => 'SAR']);
    $admin = currenciesAdmin();

    $this->actingAs($admin, 'admin')
        ->deleteJson($this->centralUrl("/admin/localization/currencies/{$used->id}"))
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->deleteJson($this->centralUrl("/admin/localization/currencies/{$free->id}"))
        ->assertOk();

    expect(Currency::query()->pluck('code')->all())->toBe(['SAR']);
});

it('queues the rate sync from the "Sync now" button', function (): void {
    Queue::fake();

    $this->actingAs(currenciesAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/localization/currencies/sync'))
        ->assertOk()
        ->assertJsonPath('message', __('localization::admin.currencies.sync_queued'));

    Queue::assertPushed(SyncExchangeRates::class);
});

it('syncs exchange rates from open.er-api.com and never touches USD', function (): void {
    Http::preventStrayRequests();
    Http::fake([CurrencyService::RATES_URL => Http::response([
        'result' => 'success',
        'base_code' => 'USD',
        'rates' => ['USD' => 1, 'SAR' => 3.75, 'EGP' => 49.21, 'AED' => 'bad', 'ZZZ' => 2],
    ])]);
    $usd = usd();
    $sar = Currency::factory()->create(['code' => 'SAR']);
    $egp = Currency::factory()->create(['code' => 'EGP', 'is_active' => false]);
    $aed = Currency::factory()->create(['code' => 'AED', 'rate' => '3.672500']);

    (new SyncExchangeRates)->handle(app(CurrencyService::class));

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === CurrencyService::RATES_URL);
    expect((float) $sar->refresh()->rate)->toBe(3.75)
        ->and((float) $egp->refresh()->rate)->toBe(49.21)
        ->and((float) $aed->refresh()->rate)->toBe(3.6725)
        ->and($sar->rate_synced_at)->not->toBeNull()
        ->and((float) $usd->refresh()->rate)->toBe(1.0)
        ->and(app(CurrencyService::class)->lastSyncedAt())->not->toBeNull();
});

it('fails the sync job on a provider error without changing rates', function (array $body, int $status): void {
    Http::fake([CurrencyService::RATES_URL => Http::response($body, $status)]);
    $sar = Currency::factory()->create(['code' => 'SAR', 'rate' => '3.700000']);

    expect(fn () => (new SyncExchangeRates)->handle(app(CurrencyService::class)))->toThrow(RuntimeException::class);
    expect((float) $sar->refresh()->rate)->toBe(3.7)->and($sar->rate_synced_at)->toBeNull();
})->with([
    'server error' => [[], 500],
    'error payload' => [['result' => 'error', 'error-type' => 'invalid-key'], 200],
]);

it('schedules the rate sync daily', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => $event->description === 'localization:sync-exchange-rates');

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 6 * * *');
});

it('renders the create and edit forms', function (): void {
    $usd = usd();
    $admin = currenciesAdmin();

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/localization/currencies/create'))->assertOk()->assertSee('name="code"', false);
    $this->actingAs($admin, 'admin')->get($this->centralUrl("/admin/localization/currencies/{$usd->id}/edit"))->assertOk()->assertSee(__('localization::admin.currencies.locked'));
});
