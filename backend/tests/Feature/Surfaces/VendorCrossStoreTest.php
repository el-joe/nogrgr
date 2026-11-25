<?php

use App\Modules\Platform\Enums\DomainType;
use App\Shared\Routing\VendorPanel;

/*
| Tenant isolation of the vendor panel after a real login: a session bound to
| store A answers 404 on store B in every host mode (sub-domain, custom domain,
| central /tenant/{slug}), even though both stores have a staff member #1.
*/

beforeEach(function (): void {
    $this->acme = $this->createTenant('acme', ['name' => 'Acme Store']);
    $this->beta = $this->createTenant('beta', ['name' => 'Beta Store']);
    $this->acmeOwner = $this->createVendorOwner($this->acme, ['email' => 'owner@acme.test']);
    $this->betaOwner = $this->createVendorOwner($this->beta, ['email' => 'owner@beta.test']);
    $this->beta->domains()->create(['domain' => 'shop.beta-example.test', 'type' => DomainType::Custom, 'is_primary' => false]);
});

it('uses the same staff id in both stores (the case session binding must catch)', function (): void {
    expect($this->acmeOwner->getKey())->toBe($this->betaOwner->getKey());
});

it('keeps a store-host login of A out of B on every host', function (): void {
    $this->post($this->tenantUrl($this->acme, '/login'), ['email' => 'owner@acme.test', 'password' => 'password'])
        ->assertRedirect($this->tenantUrl($this->acme, '/admin/dashboard'));

    $this->get($this->tenantUrl($this->acme, '/admin/dashboard'))->assertOk()->assertSee('Acme Store');

    $this->get($this->tenantUrl($this->beta, '/admin/dashboard'))->assertNotFound();
    $this->get($this->tenantUrl($this->beta, '/admin'))->assertNotFound();
    $this->get('http://shop.beta-example.test/admin/dashboard')->assertNotFound();
    $this->get($this->centralPanelUrl($this->beta, 'dashboard'))->assertNotFound();

    expect(session(VendorPanel::SESSION_TENANT))->toBe($this->acme->id);
});

it('keeps a central-host login of A out of B on every host', function (): void {
    $this->post($this->centralUrl('tenant/login'), ['email' => 'owner@acme.test', 'password' => 'password'])
        ->assertRedirect($this->centralPanelUrl($this->acme, 'dashboard'));

    $this->get($this->centralPanelUrl($this->acme, 'dashboard'))->assertOk()->assertSee('Acme Store');

    $this->get($this->centralPanelUrl($this->beta, 'dashboard'))->assertNotFound();
    $this->get($this->tenantUrl($this->beta, '/admin/dashboard'))->assertNotFound();
    $this->get('http://shop.beta-example.test/admin/dashboard')->assertNotFound();
});

it('serves the owner of B on B\'s custom domain', function (): void {
    $this->actingAsVendor($this->betaOwner, $this->beta)
        ->get('http://shop.beta-example.test/admin/dashboard')
        ->assertOk()
        ->assertSee('Beta Store');
});

it('treats a session without a tenant binding as foreign', function (): void {
    $this->actingAs($this->acmeOwner, 'vendor')
        ->get($this->tenantUrl($this->acme, '/admin/dashboard'))
        ->assertNotFound();
});

it('does not expose the vendor panel data on the storefront of another store', function (): void {
    $this->actingAsVendor($this->acmeOwner, $this->acme)
        ->get($this->tenantUrl($this->beta, '/'))
        ->assertOk()
        ->assertDontSee('Acme Store');
});
