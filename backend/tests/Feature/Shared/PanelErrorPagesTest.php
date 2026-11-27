<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Shared\Http\PanelErrorPage;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Http\Request;

/*
| Panel error pages (ADM-120…124, VEN-096…099, QA-FINAL-010): the exception renderer picks
| the page by surface — admin / owner / vendor panels render their own page (shell when
| signed in), the central website and the storefront keep theirs, API / AJAX get JSON.
*/

it('renders the admin 404 inside the panel shell for a signed-in admin', function (): void {
    $this->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/no-such-page'))
        ->assertNotFound()
        ->assertSee('data-error-surface="admin"', false)
        ->assertSee('data-error-code="404"', false)
        ->assertSee(__('ui.http.errors.404_title'))
        ->assertSee(__('ui.http.errors.dashboard'))
        ->assertSee('ui-shell-main', false);
});

it('renders the admin error card for guests, per status, in arabic too', function (int $status, string $path): void {
    $html = $this->get($this->centralUrl($path))->assertStatus($status)->getContent();

    expect($html)->toContain('data-error-surface="admin"')
        ->toContain('data-error-code="'.$status.'"')
        ->not->toContain('ui-shell-main');
})->with([
    '404' => [404, '/admin/no-such-page'],
    '404 ar' => [404, '/admin/no-such-page?lang=ar'],
]);

it('renders each admin status view with its title and actions', function (string $code, string $action): void {
    $request = Request::create($this->centralUrl('/admin/catalog/products'));
    app()->instance('request', $request);

    $html = view(PanelErrorPage::VIEW, ['surface' => 'admin', 'code' => $code])->render();

    expect($html)->toContain(e(__('ui.http.errors.'.$code.'_title')))
        ->toContain(e(__('ui.http.errors.'.$action)))
        ->toContain('data-error-code="'.$code.'"');
})->with([
    '403' => ['403', 'dashboard'],
    '419' => ['419', 'sign_in'],
    '500' => ['500', 'retry'],
    '503' => ['503', 'retry'],
]);

it('uses the admin panel page for a permission error', function (): void {
    $this->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('dashboard.view');

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertForbidden()
        ->assertSee('data-error-surface="admin"', false)
        ->assertSee(__('ui.http.errors.403_title'));
});

it('renders the vendor panel 404 (not the storefront page) on the store host and on central', function (): void {
    $tenant = $this->createTenant('errshop');

    $this->get($this->tenantUrl($tenant, '/admin/sales/orders/3'))
        ->assertNotFound()
        ->assertSee('data-error-surface="vendor"', false)
        ->assertSee(__('ui.http.errors.404_title'))
        ->assertDontSee('data-theme-family', false);

    $this->get($this->centralUrl('/tenant/errshop/no-such-page'))
        ->assertNotFound()
        ->assertSee('data-error-surface="vendor"', false)
        ->assertSee('/tenant/errshop', false);
});

it('renders the owner portal 404 in its panel shell', function (): void {
    $owner = Owner::factory()->create();

    $this->actingAs($owner, 'owner')
        ->get($this->centralUrl('/my-account/no-such-page'))
        ->assertNotFound()
        ->assertSee('data-error-surface="owner"', false)
        ->assertSee('ui-shell-main', false);
});

it('keeps the central website and storefront pages', function (): void {
    $this->get($this->centralUrl('/no-such-page'))
        ->assertNotFound()
        ->assertDontSee('data-error-surface', false);

    expect(PanelErrorPage::surface(Request::create($this->centralUrl('/affiliate/x'))))->toBeNull()
        ->and(PanelErrorPage::surface(Request::create($this->centralUrl('/tenant/login'))))->toBeNull()
        ->and(PanelErrorPage::surface(Request::create('http://shop.example.test/cart')))->toBeNull()
        ->and(PanelErrorPage::surface(Request::create('http://shop.example.test/admin/x')))->toBe('vendor');
});

it('answers JSON for API and AJAX panel requests', function (): void {
    $this->getJson($this->centralUrl('/admin/no-such-page'))
        ->assertNotFound()
        ->assertJsonStructure(['message']);

    $this->get('http://'.CentralDomains::primary().'/api/mobile/v1/nothing-here')
        ->assertNotFound()
        ->assertJson(['success' => false]);
});
