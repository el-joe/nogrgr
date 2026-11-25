<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Storefront motion gallery (/admin/ui-kit/storefront-motion): same guard and permission
| as the component library, rendered through the chosen family's storefront layout.
*/

function motionDemoAdmin(bool $withPermission = true): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $withPermission ? $admin->assignRole('super-admin') : $admin->givePermissionTo('dashboard.view');

    return $admin;
}

it('redirects guests to the admin login', function (): void {
    $this->get($this->centralUrl('/admin/ui-kit/storefront-motion'))
        ->assertRedirect($this->centralUrl('/admin/login'));
});

it('forbids admins without system.ui-kit.view', function (): void {
    $this->actingAs(motionDemoAdmin(false), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion'))
        ->assertForbidden();
});

it('renders every motion module through the default family storefront layout', function (): void {
    $this->actingAs(motionDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion'))
        ->assertOk()
        ->assertSee('data-surface="storefront"', false)
        ->assertSee('data-theme-family="elora"', false)
        ->assertSee('data-motion-root', false)
        ->assertSee('data-sticky-header', false)
        ->assertSee('data-hero=', false)
        ->assertSee('data-marquee=', false)
        ->assertSee('data-rail', false)
        ->assertSee('data-spotlight=\'{"layout":"center"}\'', false)
        ->assertSee('data-spotlight=\'{"layout":"left"}\'', false)
        ->assertSee('data-tabs-swipe', false)
        ->assertSee('data-gallery', false)
        ->assertSee('data-countdown=', false)
        ->assertSee('data-add-to-cart', false)
        ->assertSee('data-swipe-remove', false)
        ->assertSee('data-sheet-open="sheet-filters"', false)
        ->assertSee('data-parallax', false)
        ->assertSee('data-reveal-stagger', false)
        ->assertSee('data-motion-pause', false)
        ->assertSee(__('platform::motion-demo.products.headphones.name'));
});

it('switches the storefront family', function (string $family): void {
    $this->actingAs(motionDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion?family='.$family))
        ->assertOk()
        ->assertSee('data-theme-family="'.$family.'"', false);
})->with(['ecommet', 'elora', 'souqify']);

it('rejects unknown families', function (): void {
    $this->actingAs(motionDemoAdmin(), 'admin')
        ->from($this->centralUrl('/admin/ui-kit'))
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion?family=unknown'))
        ->assertRedirect($this->centralUrl('/admin/ui-kit'))
        ->assertSessionHasErrors('family');
});

it('renders right-to-left in Arabic with dir=rtl', function (): void {
    $this->actingAs(motionDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion?family=souqify&dir=rtl'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ar"', false)
        ->assertSee(__('platform::motion-demo.title', [], 'ar'))
        ->assertDontSee('٠', false);
});

it('opens the gallery on the chosen product', function (): void {
    $this->actingAs(motionDemoAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit/storefront-motion?product=perfume'))
        ->assertOk()
        ->assertSee('data-vt-name="product-perfume"', false);
});
