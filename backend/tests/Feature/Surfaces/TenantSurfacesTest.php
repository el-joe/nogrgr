<?php

use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Support\Facades\DB;

it('serves the tenant storefront home on the tenant sub-domain', function (): void {
    $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);

    $this->get($this->tenantUrl($tenant, '/'))
        ->assertOk()
        ->assertSee('Acme Store');
});

it('redirects guests from the store-host vendor panel to /login', function (): void {
    $tenant = $this->createTenant('acme');

    $this->get($this->tenantUrl($tenant, '/admin'))
        ->assertRedirect($this->tenantUrl($tenant, '/login'));

    $this->get($this->tenantUrl($tenant, '/admin/dashboard'))
        ->assertRedirect($this->tenantUrl($tenant, '/login'));

    $this->get($this->tenantUrl($tenant, '/login'))->assertOk();
});

it('lets the store owner into the store-host vendor panel', function (): void {
    $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
    $owner = $this->createVendorOwner($tenant);

    $this->actingAsVendor($owner, $tenant)
        ->get($this->tenantUrl($tenant, '/admin'))
        ->assertRedirect($this->tenantUrl($tenant, '/admin/dashboard'));

    $this->actingAsVendor($owner, $tenant)
        ->get($this->tenantUrl($tenant, '/admin/dashboard'))
        ->assertOk()
        ->assertSee('Acme Store');
});

it('serves customer auth under /account on the storefront', function (): void {
    $tenant = $this->createTenant('acme');

    $this->get($this->tenantUrl($tenant, '/account/login'))->assertOk();
    $this->get($this->tenantUrl($tenant, '/account/register'))->assertOk();
});

it('returns 404 for unknown store hosts', function (): void {
    $this->get('http://unknown-store.localhost/')->assertNotFound();
    $this->get('http://unknown-store.localhost/login')->assertNotFound();
});

it('identifies the tenant from the X-Tenant header on the mobile API', function (): void {
    $tenant = $this->createTenant('acme');

    $this->getJson('/api/mobile/v1/ping', ['X-Tenant' => (string) $tenant->id])
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'pong', 'data' => ['tenant' => $tenant->id]]);
});

it('keeps each tenant in its own database', function (): void {
    $first = $this->createTenant('first');
    $second = $this->createTenant('second');

    $this->initializeTenancy($first);
    VendorStaff::factory()->create(['email' => 'owner@first.test']);
    $firstDatabase = DB::connection()->getDatabaseName();
    $this->endTenancy();

    $this->initializeTenancy($second);
    expect(VendorStaff::query()->where('email', 'owner@first.test')->exists())->toBeFalse()
        ->and(DB::connection()->getDatabaseName())->not->toBe($firstDatabase);
    $this->endTenancy();
});
