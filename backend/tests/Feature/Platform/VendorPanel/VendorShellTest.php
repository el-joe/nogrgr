<?php

use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Support\Facades\Blade;

/*
| VEN-006 shell in the client Tenant Panel design (decision #101): the 11 flat side-menu
| items of 12:15498 in order, sub-pages listed only under the active item, the top bar
| of 12:15522 with the store menu that carries Support / Help center / Notifications.
*/

it('ships the eleven client side-menu items in the Figma order', function (): void {
    $items = collect(config('navigation.vendor'))->flatMap(fn (array $section): array => $section['items']);

    expect($items->pluck('key')->all())->toBe([
        'home', 'catalog.opportunities', 'catalog', 'sales', 'customers', 'store',
        'marketing', 'analytics', 'requests', 'partner', 'settings',
    ])->and($items->pluck('icon')->every(fn (string $icon): bool => str_starts_with($icon, 'tp-')))->toBeTrue();
});

it('lists sub-pages only under the active item and links a group to its first sub-page', function (): void {
    $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
    $owner = $this->createVendorOwner($tenant);

    $response = $this->actingAsVendor($owner, $tenant)
        ->get($this->tenantUrl($tenant, 'admin/sales/orders'))
        ->assertOk()
        ->assertSee('data-sidebar-pinned', false)
        ->assertSee('id="nav-sub-sales"', false)
        ->assertDontSee('id="nav-sub-catalog"', false)
        ->assertSee('vnd-logo-mark', false);

    $html = $response->getContent();

    expect($html)->toMatch('#<li data-nav-item="catalog">\s*<a href="'.preg_quote($this->tenantUrl($tenant, 'admin/catalog/products'), '#').'"#')
        ->and($html)->toContain('aria-current="page"');
});

it('puts support, help center and notifications in the store menu by permission', function (): void {
    $tenant = $this->createTenant('acme');
    $owner = $this->createVendorOwner($tenant);

    $this->actingAsVendor($owner, $tenant)
        ->get($this->tenantUrl($tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertSee(__('platform::vendor.topbar.store_menu'))
        ->assertSee($this->tenantUrl($tenant, 'admin/support'), false)
        ->assertSee(__('navigation.vendor.help_center'));

    $staff = $tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo('dashboard.view');

        return $staff;
    });

    $this->actingAsVendor($staff, $tenant)
        ->get($this->tenantUrl($tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertDontSee($this->tenantUrl($tenant, 'admin/support'), false)
        ->assertDontSee(__('platform::vendor.topbar.country'));
});

it('exposes the size, status and pill hooks the vendor kit styles', function (): void {
    expect(Blade::render('<x-ui::button size="xl">Go</x-ui::button>'))->toContain('ui-btn-xl', 'h-14')
        ->and(Blade::render('<x-ui::button>Go</x-ui::button>'))->toContain('ui-btn-md')
        ->and(Blade::render('<x-ui::status status="processing" />'))->toContain('data-status="processing"', 'ui-badge')
        ->and(Blade::render('<x-ui::page-header title="T" :breadcrumbs="[\'A\' => null]" />'))->toContain('data-crumbs="1"', 'ui-page-title')
        ->and(Blade::render('<x-ui::tabs id="t" variant="pill" :items="[\'a\' => \'A\', \'b\' => \'B\']" />'))->toContain('ui-tabs-pill', 'ui-tab-pill');
});
