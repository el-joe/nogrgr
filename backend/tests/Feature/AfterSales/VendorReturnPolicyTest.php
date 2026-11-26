<?php

use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Support\Str;

/*
| VEN-079 Vendor › Settings › Return policy: permissions, "stricter, never looser" validation
| against the platform default, the effective policy (store override applied on read, platform
| categories always kept), the storefront policy text and isolation between stores.
*/

beforeEach(function (): void {
    $this->platform = ReturnPolicy::query()->orderBy('id')->first() ?? ReturnPolicy::query()->create([]);
    $this->beauty = Category::factory()->create(['name' => ['en' => 'Beauty', 'ar' => 'تجميل']]);
    $this->underwear = Category::factory()->create(['name' => ['en' => 'Underwear', 'ar' => 'ملابس داخلية']]);
    $this->platform->forceFill(['window_days' => 14, 'restocking_fee_bps' => 200, 'non_returnable_category_ids' => [$this->beauty->id], 'allow_exchanges' => true])->save();
    $this->tenant = $this->createTenant('ret-'.Str::lower(Str::random(6)));
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/return-policy'.$path);
    $this->effective = fn (): ReturnPolicy => $this->tenant->run(fn () => app(ReturnPolicyService::class)->policy());
});

function returnPolicyPayload(array $overrides = []): array
{
    return array_merge([
        'window_days' => 7,
        'restocking_fee' => '5',
        'non_returnable_category_ids' => [],
        'allow_exchanges' => '1',
        'text' => ['en' => 'Returns accepted within 7 days in original packaging.', 'ar' => 'نقبل الإرجاع خلال 7 أيام بالتغليف الأصلي.'],
    ], $overrides);
}

it('guards the page and the save by the settings.return-policy permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.return-policy.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('after-sales::vendor_policy.using_default'))->assertDontSee(__('after-sales::vendor_policy.save'));
    $this->putJson(($this->url)(), returnPolicyPayload())->assertForbidden();
});

it('refuses a looser policy than the platform default', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), returnPolicyPayload(['window_days' => 30, 'restocking_fee' => '1', 'non_returnable_category_ids' => [999999]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['window_days', 'restocking_fee', 'non_returnable_category_ids.0'])
        ->assertJsonPath('errors.window_days.0', __('after-sales::vendor_policy.errors.window', ['days' => 14]));
});

it('saves a stricter policy and applies it as the store effective policy', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->putJson(($this->url)(), returnPolicyPayload(['non_returnable_category_ids' => [$this->underwear->id], 'allow_exchanges' => '0']))->assertOk();

    $policy = ($this->effective)();
    expect($policy->window_days)->toBe(7)
        ->and($policy->restocking_fee_bps)->toBe(500)
        ->and($policy->non_returnable_category_ids)->toEqualCanonicalizing([$this->beauty->id, $this->underwear->id])
        ->and($policy->allow_exchanges)->toBeFalse()
        ->and($policy->exists)->toBeFalse();

    // The platform row itself is untouched.
    expect(ReturnPolicy::query()->find($this->platform->id)->window_days)->toBe(14);

    expect($this->tenant->run(fn () => app(ReturnPolicyService::class)->storeText('ar')))->toBe('نقبل الإرجاع خلال 7 أيام بالتغليف الأصلي.');
    $this->get(($this->url)())->assertOk()->assertSee('Returns accepted within 7 days in original packaging.')->assertDontSee(__('after-sales::vendor_policy.using_default'));
});

it('never lets an older override loosen a later platform tightening', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)(), returnPolicyPayload(['window_days' => 10]))->assertOk();

    $this->platform->forceFill(['window_days' => 5, 'allow_exchanges' => false])->save();

    $policy = ($this->effective)();
    expect($policy->window_days)->toBe(5)->and($policy->allow_exchanges)->toBeFalse();
});

it('keeps the override per store and refuses another store panel', function (): void {
    $other = $this->createTenant('ret-o-'.Str::lower(Str::random(6)));
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->putJson(($this->url)(), returnPolicyPayload())->assertOk();

    expect($other->run(fn () => app(ReturnPolicyService::class)->policy()->window_days))->toBe(14)
        ->and($other->run(fn () => app(ReturnPolicyService::class)->storeText()))->toBeNull();
    $this->get($this->tenantUrl($other, 'admin/settings/return-policy'))->assertNotFound();
});
