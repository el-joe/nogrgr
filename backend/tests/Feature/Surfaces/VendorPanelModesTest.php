<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaffDirectoryEntry;
use App\Modules\Identity\Services\VendorAuthService;
use App\Modules\Identity\Services\VendorStaffDirectory;
use App\Modules\Platform\Models\AuditEvent;
use App\Shared\Routing\VendorPanel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/*
| The vendor panel in both host modes:
|   store host    http://acme.localhost/admin/...   login http://acme.localhost/login
|   central host  http://localhost/tenant/acme/...  login http://localhost/tenant/login
*/

describe('central host mode', function (): void {
    it('serves the dashboard at /tenant/{store}/dashboard for the store owner', function (): void {
        $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->centralPanelUrl($tenant))
            ->assertRedirect($this->centralPanelUrl($tenant, 'dashboard'));

        $this->actingAsVendor($owner, $tenant)
            ->get($this->centralPanelUrl($tenant, 'dashboard'))
            ->assertOk()
            ->assertSee('Acme Store');
    });

    it('redirects guests to the central vendor login with the store hint', function (): void {
        $tenant = $this->createTenant('acme');

        $this->get($this->centralPanelUrl($tenant, 'dashboard'))
            ->assertRedirect($this->centralUrl('tenant/login?store=acme'));

        $this->get($this->centralUrl('tenant/login'))->assertOk();
    });

    it('returns 404 for an unknown store slug', function (): void {
        $this->get($this->centralUrl('tenant/nope/dashboard'))->assertNotFound();
    });

    it('returns 404 when a vendor of store A opens store B', function (): void {
        $acme = $this->createTenant('acme');
        $beta = $this->createTenant('beta');
        $owner = $this->createVendorOwner($acme);

        $this->actingAsVendor($owner, $acme)
            ->get($this->centralPanelUrl($beta, 'dashboard'))
            ->assertNotFound();

        $this->actingAsVendor($owner, $acme)
            ->get($this->centralPanelUrl($beta))
            ->assertNotFound();
    });
});

describe('store host isolation', function (): void {
    it('returns 404 when a vendor session of store A reaches store B\'s domain', function (): void {
        $acme = $this->createTenant('acme');
        $beta = $this->createTenant('beta');
        $owner = $this->createVendorOwner($acme);

        $this->actingAsVendor($owner, $acme)
            ->get($this->tenantUrl($beta, '/admin/dashboard'))
            ->assertNotFound();
    });

    it('does not serve the vendor panel on central domains under /admin', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->centralUrl('/admin/dashboard'))
            ->assertNotFound();
    });
});

describe('url generation', function (): void {
    it('generates links for the current mode from the same view', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, '/admin/dashboard'))
            ->assertSee('href="http://acme.localhost/admin/dashboard"', false)
            ->assertSee('action="http://acme.localhost/logout"', false);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->centralPanelUrl($tenant, 'dashboard'))
            ->assertSee('href="http://localhost/tenant/acme/dashboard"', false)
            ->assertSee('action="http://localhost/tenant/logout"', false);
    });

    it('builds central-host urls explicitly for a store', function (): void {
        expect(VendorPanel::centralUrl('acme'))->toBe('http://localhost/tenant/acme/dashboard')
            ->and(VendorPanel::centralUrl('acme', 'login'))->toBe('http://localhost/tenant/login?store=acme');
    });
});

describe('store host login', function (): void {
    it('signs a vendor in on /login and keeps them on the store host', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->post($this->tenantUrl($tenant, '/login'), ['email' => 'owner@acme.test', 'password' => 'password'])
            ->assertRedirect($this->tenantUrl($tenant, '/admin/dashboard'));

        expect(session(VendorPanel::SESSION_TENANT))->toBe($tenant->id);

        $this->get($this->tenantUrl($tenant, '/admin/dashboard'))->assertOk();

        $this->post($this->tenantUrl($tenant, '/logout'))
            ->assertRedirect($this->tenantUrl($tenant, '/login'));

        $this->get($this->tenantUrl($tenant, '/admin/dashboard'))
            ->assertRedirect($this->tenantUrl($tenant, '/login'));
    });

    it('rejects a wrong password with the generic message', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->from($this->tenantUrl($tenant, '/login'))
            ->post($this->tenantUrl($tenant, '/login'), ['email' => 'owner@acme.test', 'password' => 'wrong'])
            ->assertRedirect($this->tenantUrl($tenant, '/login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        expect(Auth::guard('vendor')->check())->toBeFalse();
    });
});

describe('central host login', function (): void {
    it('logs a single-store vendor straight into their store', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $this->post($this->centralUrl('tenant/login'), ['email' => 'Owner@Acme.test', 'password' => 'password'])
            ->assertRedirect($this->centralPanelUrl($tenant, 'dashboard'));

        $this->get($this->centralPanelUrl($tenant, 'dashboard'))->assertOk();

        $this->post($this->centralUrl('tenant/logout'))
            ->assertRedirect($this->centralUrl('tenant/login'));

        $this->get($this->centralPanelUrl($tenant, 'dashboard'))
            ->assertRedirect($this->centralUrl('tenant/login?store=acme'));
    });

    it('shows a store picker when the email belongs to several stores', function (): void {
        $acme = $this->createTenant('acme', ['name' => 'Acme Store']);
        $beta = $this->createTenant('beta', ['name' => 'Beta Store']);
        $gamma = $this->createTenant('gamma', ['name' => 'Gamma Store']);
        $this->createVendorOwner($acme, ['email' => 'multi@example.test']);
        $this->createVendorOwner($beta, ['email' => 'multi@example.test']);
        $this->createVendorOwner($gamma, ['email' => 'multi@example.test', 'password' => 'different-password']);

        $this->post($this->centralUrl('tenant/login'), ['email' => 'multi@example.test', 'password' => 'password'])
            ->assertRedirect($this->centralUrl('tenant/stores'));

        $this->get($this->centralUrl('tenant/stores'))
            ->assertOk()
            ->assertSee('Acme Store')
            ->assertSee('Beta Store')
            ->assertDontSee('Gamma Store');

        expect(Auth::guard('vendor')->check())->toBeFalse();

        $this->post($this->centralUrl('tenant/stores'), ['tenant_id' => $gamma->id])
            ->assertSessionHasErrors('tenant_id');

        $this->post($this->centralUrl('tenant/stores'), ['tenant_id' => $beta->id])
            ->assertRedirect($this->centralPanelUrl($beta, 'dashboard'));

        $this->get($this->centralPanelUrl($beta, 'dashboard'))->assertOk()->assertSee('Beta Store');
        $this->get($this->centralPanelUrl($acme, 'dashboard'))->assertNotFound();
    });

    it('uses the store hint to skip the picker', function (): void {
        $acme = $this->createTenant('acme');
        $beta = $this->createTenant('beta');
        $this->createVendorOwner($acme, ['email' => 'multi@example.test']);
        $this->createVendorOwner($beta, ['email' => 'multi@example.test']);

        $this->post($this->centralUrl('tenant/login'), ['email' => 'multi@example.test', 'password' => 'password', 'store' => 'beta'])
            ->assertRedirect($this->centralPanelUrl($beta, 'dashboard'));
    });

    it('does not reveal whether an email exists', function (): void {
        $tenant = $this->createTenant('acme');
        $this->createVendorOwner($tenant, ['email' => 'owner@acme.test']);

        $unknown = $this->from($this->centralUrl('tenant/login'))
            ->post($this->centralUrl('tenant/login'), ['email' => 'nobody@example.test', 'password' => 'password']);
        $wrong = $this->from($this->centralUrl('tenant/login'))
            ->post($this->centralUrl('tenant/login'), ['email' => 'owner@acme.test', 'password' => 'wrong']);

        $unknown->assertRedirect($this->centralUrl('tenant/login'))->assertSessionHasErrors(['email' => __('auth.failed')]);
        $wrong->assertRedirect($this->centralUrl('tenant/login'))->assertSessionHasErrors(['email' => __('auth.failed')]);
    });

    it('throttles repeated failures', function (): void {
        foreach (range(1, VendorAuthService::MAX_ATTEMPTS) as $attempt) {
            $this->post($this->centralUrl('tenant/login'), ['email' => 'nobody@example.test', 'password' => 'x'.$attempt]);
        }

        $this->from($this->centralUrl('tenant/login'))
            ->post($this->centralUrl('tenant/login'), ['email' => 'nobody@example.test', 'password' => 'again'])
            ->assertSessionHasErrors('email');

        expect(RateLimiter::tooManyAttempts('vendor-login:'.sha1('nobody@example.test|127.0.0.1'), VendorAuthService::MAX_ATTEMPTS))->toBeTrue();
    });
});

describe('vendor staff directory', function (): void {
    it('tracks staff create, email change and delete', function (): void {
        $tenant = $this->createTenant('acme');
        $staff = $this->createVendorOwner($tenant, ['email' => 'first@acme.test']);

        $entry = VendorStaffDirectoryEntry::withoutTenantScope()->sole();
        expect($entry->tenant_id)->toBe($tenant->id)
            ->and($entry->vendor_staff_id)->toBe($staff->id)
            ->and($entry->email_hash)->toBe(VendorStaffDirectory::hashEmail('FIRST@acme.test'))
            ->and($entry->email_hash)->not->toContain('first');

        $this->initializeTenancy($tenant);
        $staff->update(['email' => 'second@acme.test']);
        expect(VendorStaffDirectoryEntry::withoutTenantScope()->sole()->email_hash)->toBe(VendorStaffDirectory::hashEmail('second@acme.test'));

        $staff->delete();
        $this->endTenancy();

        expect(VendorStaffDirectoryEntry::withoutTenantScope()->count())->toBe(0);
    });
});

describe('admin impersonation', function (): void {
    it('opens the store in central host mode and audits it', function (): void {
        $this->seed(CentralPermissionSeeder::class);
        $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
        $owner = $this->createVendorOwner($tenant);

        $admin = Admin::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'admin')
            ->post($this->centralUrl("admin/tenants/{$tenant->id}/impersonate"))
            ->assertRedirect($this->centralPanelUrl($tenant, 'dashboard'));

        $this->get($this->centralPanelUrl($tenant, 'dashboard'))->assertOk()->assertSee('Acme Store');

        $event = AuditEvent::withoutTenantScope()->where('action', 'auth.impersonated')->sole();
        expect($event->tenant_id)->toBe($tenant->id)
            ->and($event->subject_id)->toBe((string) $owner->id)
            ->and($event->changes['impersonator_admin_id'])->toBe($admin->id);
    });

    it('requires the impersonation permission', function (): void {
        $this->seed(CentralPermissionSeeder::class);
        $tenant = $this->createTenant('acme');

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post($this->centralUrl("admin/tenants/{$tenant->id}/impersonate"))
            ->assertForbidden();
    });
});
