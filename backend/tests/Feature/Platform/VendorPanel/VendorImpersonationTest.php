<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;

/*
| VEN-005: admin impersonation through a one-time signed accept link, the
| "impersonating" banner, End session and the session time limit — all audited.
*/

function startImpersonation(object $test, Tenant $tenant): array
{
    $test->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create(['name' => 'Sara Admin']);
    $admin->assignRole('super-admin');

    $response = $test->actingAs($admin, 'admin')->post('http://localhost/admin/tenants/'.$tenant->id.'/impersonate');
    $response->assertRedirect();

    return [$admin, (string) $response->headers->get('Location')];
}

it('issues a signed one-time accept link that opens the store with the banner', function (): void {
    $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
    $owner = $this->createVendorOwner($tenant);
    [$admin, $link] = startImpersonation($this, $tenant);

    expect($link)->toContain('/tenant/impersonate/')->toContain('signature=');

    $this->get($link)->assertRedirect($this->centralPanelUrl($tenant, 'dashboard'));

    $this->get($this->centralPanelUrl($tenant, 'dashboard'))
        ->assertOk()
        ->assertSee('data-impersonation-banner', false)
        ->assertSee('Sara Admin');

    $event = AuditEvent::withoutTenantScope()->where('action', 'auth.impersonated')->where('subject_id', (string) $owner->id)->sole();
    expect($event->changes['impersonator_admin_id'])->toBe($admin->id);

    // Used once: the same link now shows the expired state.
    $this->get($link)->assertStatus(410)->assertSee('data-impersonation-expired', false);
});

it('rejects an expired or tampered link', function (): void {
    $tenant = $this->createTenant('acme');
    $this->createVendorOwner($tenant);
    [, $link] = startImpersonation($this, $tenant);

    $this->get($link.'0')->assertStatus(410);

    $this->travel(3)->minutes();
    $this->get($link)->assertStatus(410)->assertSee(__('identity::vendor_auth.impersonation.expired_title'));
    $this->assertGuest('vendor');
});

it('ends the session from the banner, audited, back to the vendor profile', function (): void {
    $tenant = $this->createTenant('acme');
    $this->createVendorOwner($tenant);
    [$admin, $link] = startImpersonation($this, $tenant);
    $this->get($link);

    $this->post($this->centralPanelUrl($tenant, 'impersonation/leave'))
        ->assertRedirect('http://localhost/admin/vendors/'.$tenant->id);

    $this->assertGuest('vendor');
    $this->assertAuthenticatedAs($admin, 'admin');
    $ended = AuditEvent::withoutTenantScope()->where('action', 'auth.impersonation_ended')->sole();
    expect($ended->changes['impersonator_admin_id'])->toBe($admin->id)
        ->and($ended->changes['reason'])->toBe('ended');
});

it('closes the session when the time limit passes', function (): void {
    $tenant = $this->createTenant('acme');
    $this->createVendorOwner($tenant);
    [, $link] = startImpersonation($this, $tenant);
    $this->get($link);

    $this->travel(31)->minutes();

    $this->get($this->centralPanelUrl($tenant, 'dashboard'))
        ->assertRedirect('http://localhost/admin/vendors/'.$tenant->id);
    $this->assertGuest('vendor');
    expect(AuditEvent::withoutTenantScope()->where('action', 'auth.impersonation_ended')->sole()->changes['reason'])->toBe('expired');
});

it('forbids leave for a normal vendor session', function (): void {
    $tenant = $this->createTenant('acme');
    $owner = $this->createVendorOwner($tenant);

    $this->actingAsVendor($owner, $tenant)
        ->post($this->tenantUrl($tenant, 'admin/impersonation/leave'))
        ->assertForbidden();
});
