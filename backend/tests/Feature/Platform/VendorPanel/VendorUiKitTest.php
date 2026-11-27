<?php

use App\Modules\Identity\Services\VendorImpersonationService;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Identity\Support\PermissionMatrix;
use App\Shared\Routing\VendorPanel;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-093 vendor UI kit (decision #116): internal page, never granted to store roles by
| default; reachable by an impersonating platform admin or the internal ui-kit.view;
| renders every vendor-kit section, the demo table JSON and the 422 form contract.
*/

beforeEach(function (): void {
    [$this->tenant, $this->owner] = catalogStore('kit-'.Str::lower(Str::random(6)));
    $this->url = fn (string $path = 'admin/ui-kit') => $this->tenantUrl($this->tenant, $path);
});

it('redirects guests to the vendor sign-in', function (): void {
    $this->get(($this->url)())->assertRedirect();
});

it('forbids the store owner and staff by default', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)())->assertForbidden();
    $this->actingAsVendor($this->owner, $this->tenant)->getJson(($this->url)('admin/ui-kit/table'))->assertForbidden();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('admin/ui-kit/form'), [])->assertForbidden();

    expect($this->tenant->run(fn () => $this->owner->fresh()->hasPermissionTo('ui-kit.view', 'vendor')))->toBeFalse();
});

it('keeps ui-kit.view out of the role editors', function (): void {
    expect(PermissionCatalog::forGuard('vendor'))->toContain('ui-kit.view')
        ->and(PermissionCatalog::assignable('vendor'))->not->toContain('ui-kit.view')
        ->and(collect(PermissionMatrix::for('vendor')['modules'])->flatten()->all())->not->toContain('ui-kit.view');
});

it('opens for an impersonating platform admin with every section', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)
        ->withSession([VendorPanel::SESSION_IMPERSONATOR => 1, VendorImpersonationService::SESSION_EXPIRES => now()->addMinutes(10)->getTimestamp()])
        ->get(($this->url)())
        ->assertOk()
        ->assertSee('data-page="vendor-ui-kit"', false)
        ->assertSee(__('platform::vendor_ui_kit.sections.foundations'))
        ->assertSee(__('platform::vendor_ui_kit.sections.forms'))
        ->assertSee(__('platform::vendor_ui_kit.sections.controls'))
        ->assertSee(__('platform::vendor_ui_kit.sections.table'))
        ->assertSee(__('platform::vendor_ui_kit.sections.overlays'))
        ->assertSee(__('platform::vendor_ui_kit.sections.components'))
        ->assertSee('id="vendor-ui-kit-products"', false);
});

it('opens for a member given the internal permission, with table JSON and the 422 contract', function (): void {
    $staff = catalogStaff($this->tenant, ['ui-kit.view']);

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)())->assertOk();
    $this->actingAsVendor($staff, $this->tenant)->getJson(($this->url)('admin/ui-kit/table?draw=1&start=0&length=10'))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 36);
    $this->actingAsVendor($staff, $this->tenant)->postJson(($this->url)('admin/ui-kit/form'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_name', 'market']);
    $this->actingAsVendor($staff, $this->tenant)->postJson(($this->url)('admin/ui-kit/form'), ['product_name' => 'Nike shoes', 'market' => 'sa'])
        ->assertOk();
});

it('shows the nav entry only to those who may open the kit', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get($this->tenantUrl($this->tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertDontSee(__('navigation.vendor.ui_kit'));

    $this->actingAsVendor($this->owner, $this->tenant)
        ->withSession([VendorPanel::SESSION_IMPERSONATOR => 1, VendorImpersonationService::SESSION_EXPIRES => now()->addMinutes(10)->getTimestamp()])
        ->get($this->tenantUrl($this->tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertSee(__('navigation.vendor.ui_kit'));
});
