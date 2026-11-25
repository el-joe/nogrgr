<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Models\TenantNotificationRecord;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| VEN-006 shell, VEN-007 / 007a / 007b onboarding, VEN-008 setup widget, dashboard.
*/

function storeSetting(Tenant $tenant, StoreSettingKey $key): mixed
{
    return $tenant->run(fn () => StoreSetting::query()->where('key', $key->value)->value('value'));
}

describe('panel shell and dashboard', function (): void {
    it('renders the dashboard with the shell, setup widget and permission-filtered nav', function (): void {
        $tenant = $this->createTenant('acme', ['name' => 'Acme Store']);
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/dashboard'))
            ->assertOk()
            ->assertSee(__('platform::dashboard_vendor.welcome', ['store' => 'Acme Store']))
            ->assertSee('vnd-sidebar', false)
            ->assertSee(__('platform::onboarding.widget.title'))
            ->assertSee(__('platform::dashboard_vendor.hero.power'))
            ->assertSee($this->tenantUrl($tenant, 'admin/onboarding'), false)
            ->assertSee('data-nav-badge="setup"', false)
            ->assertSee('http://acme.'.CentralDomains::primary(), false);
    });

    it('hides nav items the member may not open', function (): void {
        $tenant = $this->createTenant('acme');
        $staff = $tenant->run(function (): VendorStaff {
            $staff = VendorStaff::factory()->create();
            $staff->givePermissionTo('dashboard.view');

            return $staff;
        });

        $this->actingAsVendor($staff, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/dashboard'))
            ->assertOk()
            ->assertSee(__('navigation.vendor.onboarding'))
            ->assertDontSee(__('navigation.vendor.subscription'));
    });

    it('lands the owner SSO from the owner portal on the dashboard', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->centralPanelUrl($tenant, 'dashboard'))
            ->assertOk()
            ->assertSee(__('platform::onboarding.widget.title'));
    });

    it('isolates stores: a member of acme gets 404 on beta', function (): void {
        $acme = $this->createTenant('acme');
        $beta = $this->createTenant('beta');
        $owner = $this->createVendorOwner($acme);

        $this->actingAsVendor($owner, $acme)->get($this->tenantUrl($beta, 'admin/onboarding'))->assertNotFound();
        $this->actingAsVendor($owner, $acme)->post($this->tenantUrl($beta, 'admin/onboarding/tour/complete'))->assertNotFound();
    });

    it('serves the bell from the store inbox and marks everything read', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $tenant->run(fn () => TenantNotificationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'orders.created',
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
            'data' => ['title' => 'New order #1001', 'body' => 'Paid online'],
        ]));

        $this->actingAsVendor($owner, $tenant)
            ->getJson($this->tenantUrl($tenant, 'admin/notifications/latest'))
            ->assertOk()
            ->assertJsonPath('data.unread', 1)
            ->assertJsonPath('data.items.0.title', 'New order #1001');

        $this->actingAsVendor($owner, $tenant)
            ->postJson($this->tenantUrl($tenant, 'admin/notifications/read-all'))
            ->assertOk()
            ->assertJsonPath('data.unread', 0);

        expect($tenant->run(fn () => TenantNotificationRecord::query()->whereNull('read_at')->count()))->toBe(0);
    });
});

describe('onboarding (VEN-007)', function (): void {
    it('shows the tour first, then the 8-step checklist with progress', function (): void {
        $tenant = $this->createTenant('acme', ['owner_email' => 'o@acme.test', 'owner_phone' => '+966500000000', 'selling_country_ids' => [1]]);
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/onboarding'))
            ->assertOk()
            ->assertSee('data-tour', false)
            ->assertSee(__('platform::onboarding.tour.complete'));

        $response = $this->actingAsVendor($owner, $tenant)->get($this->tenantUrl($tenant, 'admin/onboarding?tab=checklist'))->assertOk();

        expect(substr_count($response->getContent(), 'data-step="'))->toBe(8);
        // store details + countries done → 2 of 8.
        $response->assertSee(__('platform::onboarding.of', ['done' => 2, 'total' => 8]));
        // Steps whose page ships later say so instead of linking.
        $response->assertSee(__('platform::onboarding.steps.theme.available_after'));
        // The import step links to the new central products page (VEN-011).
        $response->assertSee($this->tenantUrl($tenant, 'admin/catalog/products/new'), false);
    });

    it('completes and dismisses the tour', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->post($this->tenantUrl($tenant, 'admin/onboarding/tour/complete'))
            ->assertRedirect($this->tenantUrl($tenant, 'admin/onboarding?tab=checklist'));
        expect(storeSetting($tenant, StoreSettingKey::TourCompletedAt))->not->toBeNull();

        $this->actingAsVendor($owner, $tenant)->post($this->tenantUrl($tenant, 'admin/onboarding/tour/dismiss'))->assertRedirect();
        expect(storeSetting($tenant, StoreSettingKey::TourDismissedAt))->not->toBeNull();

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/onboarding'))
            ->assertSee(__('platform::onboarding.of', ['done' => 1, 'total' => 8]));
    });

    it('skips payment readiness and counts it as handled', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)->post($this->tenantUrl($tenant, 'admin/onboarding/payment-readiness/skip'))->assertRedirect();

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/onboarding?tab=checklist'))
            ->assertSee(__('platform::enums.setup_step_state.skipped'))
            ->assertSee(__('platform::onboarding.of', ['done' => 1, 'total' => 8]));
    });

    it('counts a connected custom domain', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);
        $tenant->domains()->create(['domain' => 'shop-'.Str::lower(Str::random(6)).'.test', 'type' => DomainType::Custom, 'is_primary' => false]);

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/onboarding?tab=checklist'))
            ->assertSee(__('platform::onboarding.of', ['done' => 1, 'total' => 8]));
    });

    it('requires onboarding.manage for the actions', function (): void {
        $tenant = $this->createTenant('acme');
        $staff = $tenant->run(function (): VendorStaff {
            $staff = VendorStaff::factory()->create();
            $staff->givePermissionTo('dashboard.view');

            return $staff;
        });

        $this->actingAsVendor($staff, $tenant)->get($this->tenantUrl($tenant, 'admin/onboarding'))->assertOk();
        $this->actingAsVendor($staff, $tenant)->post($this->tenantUrl($tenant, 'admin/onboarding/tour/complete'))->assertForbidden();
        $this->actingAsVendor($staff, $tenant)->post($this->tenantUrl($tenant, 'admin/onboarding/payment-readiness/skip'))->assertForbidden();
        $this->actingAsVendor($staff, $tenant)->post($this->tenantUrl($tenant, 'admin/onboarding/logo'))->assertForbidden();
    });
});

describe('logo upload (VEN-007b)', function (): void {
    it('stores the logo on the store disk and completes the step', function (): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->postJson($this->tenantUrl($tenant, 'admin/onboarding/logo'), ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)])
            ->assertOk()
            ->assertJsonPath('redirect', $this->tenantUrl($tenant, 'admin/onboarding?tab=checklist'));

        $path = storeSetting($tenant, StoreSettingKey::LogoPath);
        expect($path)->toBeString()->toStartWith('branding/')
            ->and($tenant->run(fn (): bool => Storage::disk('public')->exists($path)))->toBeTrue();

        $this->actingAsVendor($owner, $tenant)
            ->get($this->tenantUrl($tenant, 'admin/dashboard'))
            ->assertSee('/tenancy/assets/'.$path, false);

        $tenant->run(fn () => Storage::disk('public')->delete($path));
    });

    it('validates the logo file', function (array $payload): void {
        $tenant = $this->createTenant('acme');
        $owner = $this->createVendorOwner($tenant);

        $this->actingAsVendor($owner, $tenant)
            ->postJson($this->tenantUrl($tenant, 'admin/onboarding/logo'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');

        expect(storeSetting($tenant, StoreSettingKey::LogoPath))->toBeNull();
    })->with([
        'missing' => [fn () => []],
        'not an image' => [fn () => ['logo' => UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf')]],
        'svg' => [fn () => ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>')]],
        'too small' => [fn () => ['logo' => UploadedFile::fake()->image('logo.png', 32, 32)]],
        'too large' => [fn () => ['logo' => UploadedFile::fake()->image('logo.png', 400, 400)->size(3000)]],
    ]);
});
