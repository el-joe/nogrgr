<?php

use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Support\Str;

/*
| RQA-VEN-007: a view-only member (dashboard.view, catalog.products.view, orders.view) sees
| no link that answers 403 (dashboard "Customize store", onboarding tour "Open" stops,
| "New products" / "Sort" nav), and the manage-only GET pages need the manage permission
| like their POSTs (products/new, products/sort, edit-requests/form).
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('vo-'.Str::lower(Str::random(6)), ['name' => 'View Only Store']);
    $this->viewer = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'catalog.products.view', 'orders.view']);

        return $staff;
    });
    $this->url = fn (string $path): string => $this->tenantUrl($this->tenant, $path);
});

it('hides the dashboard and tour links the member cannot open', function (): void {
    $this->actingAsVendor($this->viewer, $this->tenant);

    $this->get(($this->url)('admin/dashboard'))->assertOk()
        ->assertDontSee(__('platform::dashboard_vendor.customize'))
        ->assertDontSee(($this->url)('admin/store/appearance'), false)
        ->assertDontSee(($this->url)('admin/catalog/products/new'), false)
        ->assertDontSee(($this->url)('admin/catalog/products/sort'), false);

    $this->get(($this->url)('admin/onboarding?tab=tour'))->assertOk()
        ->assertDontSee(($this->url)('admin/finance/subscription'), false)
        ->assertDontSee(($this->url)('admin/settings/languages'), false)
        ->assertSee(($this->url)('admin/catalog/products'), false);
});

it('keeps the links for the store owner', function (): void {
    $owner = $this->createVendorOwner($this->tenant);
    $this->actingAsVendor($owner, $this->tenant);

    $this->get(($this->url)('admin/dashboard'))->assertOk()->assertSee(($this->url)('admin/store/appearance'), false);
    $this->get(($this->url)('admin/onboarding?tab=tour'))->assertOk()
        ->assertSee(($this->url)('admin/finance/subscription'), false)
        ->assertSee(($this->url)('admin/settings/languages'), false);
    $this->get(($this->url)('admin/catalog/products/new'))->assertOk();
    $this->get(($this->url)('admin/catalog/products/sort'))->assertOk();
});

it('refuses the manage-only pages to a view-only member', function (string $path): void {
    $this->actingAsVendor($this->viewer, $this->tenant);

    $this->get(($this->url)($path))->assertForbidden();
})->with(['admin/catalog/products/new', 'admin/catalog/products/new/data', 'admin/catalog/products/sort', 'admin/catalog/edit-requests/form']);
