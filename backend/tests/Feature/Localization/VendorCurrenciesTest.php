<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\StoreCurrencyService;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Support\Str;

/*
| VEN-072 Vendor › Settings › Currencies: permissions, the store's rows (USD + its selling
| countries' platform currencies with the platform rate), Active / Default switches saved
| as exceptions, isolation, and the storefront honouring them (display only — USD charged).
*/

beforeEach(function (): void {
    $currency = fn (string $code, string $rate): Currency => tap(Currency::query()->where('code', $code)->first() ?? Currency::factory()->code($code, $rate)->create(), fn (Currency $c) => $c->forceFill(['rate' => $rate, 'rate_synced_at' => now(), 'is_active' => true, 'is_base' => $code === 'USD'])->save());
    $this->usd = $currency('USD', '1.000000');
    $this->sar = $currency('SAR', '3.750000');
    $this->aed = $currency('AED', '3.672500');
    $this->sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $this->ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
    $this->sa->forceFill(['currency_code' => 'SAR', 'is_active' => true])->save();
    $this->ae->forceFill(['currency_code' => 'AED', 'is_active' => true])->save();
    $this->tenant = $this->createTenant('cur-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/currencies'.$path);
    $this->stored = fn (): mixed => $this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::Currencies->value)->first()?->value);
});

it('guards the page and the save by the settings.currencies permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.currencies.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee('3.7500')->assertSee('3.6725')->assertSee(__('localization::vendor_currencies.all_switcher'))
        ->assertDontSee(__('localization::vendor_currencies.save'));
    $this->putJson(($this->url)(), ['active' => ['SAR']])->assertForbidden();
});

it('lists USD and the selling countries currencies, all on by default', function (): void {
    $rows = $this->tenant->run(fn () => app(StoreCurrencyService::class)->rows());

    expect(collect($rows)->map(fn (array $row): string => $row['currency']->code)->all())->toBe(['AED', 'SAR', 'USD'])
        ->and(collect($rows)->every(fn (array $row): bool => $row['active']))->toBeTrue()
        ->and($rows[1]['default'])->toBeTrue()
        ->and($rows[2]['default'])->toBeFalse();
});

it('also lists platform currencies of countries the store does not sell to, never shown by default', function (): void {
    Currency::factory()->code('EUR', '0.920000')->create(['is_active' => true, 'rate_synced_at' => now()]);

    $this->tenant->run(function (): void {
        $service = app(StoreCurrencyService::class);
        $row = collect($service->rows())->first(fn (array $row): bool => $row['currency']->code === 'EUR');

        expect($row['active'])->toBeTrue()
            ->and($row['default'])->toBeFalse()
            ->and($row['countries'])->toBe([])
            ->and($service->switcherCurrencies()->pluck('code')->all())->toContain('EUR');
    });
});

it('validates and saves active / default switches as exceptions', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), ['active' => ['EUR', 'USD']])->assertUnprocessable()->assertJsonValidationErrors(['active.0', 'active.1']);

    // AED off (so never default), SAR on but not shown by default.
    $this->putJson(($this->url)(), ['active' => ['SAR'], 'default' => ['AED']])->assertOk();
    expect(($this->stored)())->toBe(['inactive' => ['AED'], 'not_default' => ['SAR']]);

    $rows = collect($this->tenant->run(fn () => app(StoreCurrencyService::class)->rows()))->keyBy(fn (array $row): string => $row['currency']->code);
    expect($rows['AED']['active'])->toBeFalse()->and($rows['AED']['default'])->toBeFalse()
        ->and($rows['SAR']['active'])->toBeTrue()->and($rows['SAR']['default'])->toBeFalse()
        ->and($rows['USD']['active'])->toBeTrue();

    $this->get(($this->url)())->assertOk()->assertSee('data-currency-row="AED"', false);
});

it('applies the store choices to the storefront display currency and switcher', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)(), ['active' => ['SAR'], 'default' => []])->assertOk();

    $this->tenant->run(function (): void {
        $service = app(StoreCurrencyService::class);
        expect($service->switcherCurrencies()->pluck('code')->sort()->values()->all())->toBe(['SAR', 'USD'])
            ->and($service->isAvailable($this->aed))->toBeFalse()
            ->and($service->isCountryDefault($this->sar))->toBeFalse();

        // Saudi shoppers now see USD unless they pick SAR in the switcher.
        request()->setLaravelSession(app('session.store'));
        $context = app(StorefrontContext::class);
        $context->remember($this->sa->id, null);
        expect($context->currencyCode())->toBe('USD');
        $context->remember(null, 'SAR');
        expect($context->currencyCode())->toBe('SAR');
        $context->remember(null, 'AED');
        expect($context->currencyCode())->toBe('USD');
    });
});

it('keeps currency settings per store and refuses another store panel', function (): void {
    $other = $this->createTenant('cur-o-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id]]);
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)(), ['active' => [], 'default' => []])->assertOk();

    expect($other->run(fn () => app(StoreCurrencyService::class)->isAvailable($this->sar)))->toBeTrue();
    $this->get($this->tenantUrl($other, 'admin/settings/currencies'))->assertNotFound();
});
