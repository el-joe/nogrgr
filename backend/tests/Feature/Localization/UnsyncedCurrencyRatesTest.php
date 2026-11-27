<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Services\CurrencyService;
use App\Modules\Localization\Services\StoreCurrencyService;
use Spatie\Permission\PermissionRegistrar;

/*
| QA-FINAL-009: a non-base currency whose rate was never synced is hidden from shoppers
| (switcher + availability) and flagged on Admin › Currencies; it shows up once synced.
*/

it('hides never-synced currencies from shoppers until their rate is synced', function (): void {
    $synced = Currency::factory()->create(['rate' => '3.750000', 'rate_synced_at' => now()]);
    $unsynced = Currency::factory()->create(['rate' => '1.000000', 'rate_synced_at' => null]);
    $service = app(StoreCurrencyService::class);

    $codes = $service->switcherCurrencies()->pluck('code')->all();

    expect($codes)->toContain($synced->code)->not->toContain($unsynced->code)
        ->and($service->isAvailable($unsynced))->toBeFalse()
        ->and($service->isAvailable($synced))->toBeTrue()
        ->and($unsynced->hasUsableRate())->toBeFalse()
        ->and(app(CurrencyService::class)->unsyncedActiveCodes())->toContain($unsynced->code)->not->toContain($synced->code);

    $unsynced->forceFill(['rate' => '0.920000', 'rate_synced_at' => now()])->save();

    expect(app(StoreCurrencyService::class)->switcherCurrencies()->pluck('code')->all())->toContain($unsynced->code);
});

it('warns admins about active currencies that were never synced', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->assignRole('super-admin');
    $unsynced = Currency::factory()->create(['rate_synced_at' => null]);

    $this->actingAs($admin, 'admin')->get(route('admin.localization.currencies.index'))
        ->assertOk()
        ->assertSee('data-unsynced-currencies', false)
        ->assertSee($unsynced->code);

    $unsynced->forceFill(['rate_synced_at' => now()])->save();

    $this->actingAs($admin, 'admin')->get(route('admin.localization.currencies.index'))
        ->assertOk()
        ->assertDontSee('data-unsynced-currencies', false);
});
