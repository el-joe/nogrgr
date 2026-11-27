<?php

use App\Modules\Api\Services\MobileAppSettingsService;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| VEN-037 / 037a Vendor › Your store › Mobile app and ADM-041g Admin › Vendor › Mobile app
| tab: permissions, validation, save (versions, store links, maintenance per locale, splash
| colour + logo, features), the bootstrap reflecting it and store isolation.
*/

beforeEach(function (): void {
    Storage::fake('public');
    $this->tenant = $this->createTenant('mobileapp');
    $this->staff = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = ''): string => $this->tenantUrl($this->tenant, 'admin/store/mobile-app'.$path);
    $this->valid = [
        'ios_min_version' => '1.2.0',
        'ios_latest_version' => '1.4.2',
        'ios_store_url' => 'https://apps.apple.com/app/id123',
        'android_min_version' => '1.0',
        'android_latest_version' => '1.4.2',
        'android_store_url' => 'https://play.google.com/store/apps/details?id=com.shop',
        'maintenance' => '1',
        'maintenance_message' => ['en' => 'Back soon', 'ar' => 'نعود قريبًا', 'xx' => 'dropped'],
        'splash_background' => '#0B0B0B',
        'features' => ['reviews' => '1', 'wishlist' => '0', 'guest_checkout' => '1'],
    ];
});

function mobileStaff(Tenant $tenant, array $permissions): VendorStaff
{
    return $tenant->run(function () use ($permissions): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', ...$permissions]);

        return $staff;
    });
}

function mobileAdmin(array $permissions): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['dashboard.view', ...$permissions]);

    return $admin;
}

it('gates the vendor page by store.mobile-app permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $nobody = mobileStaff($this->tenant, []);
    $this->actingAsVendor($nobody, $this->tenant)->get(($this->url)())->assertForbidden();

    $viewer = mobileStaff($this->tenant, ['store.mobile-app.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)())
        ->assertOk()
        ->assertSee('data-page="vendor-mobile-app"', false)
        ->assertDontSee('form="vendor-mobile-app-form"', false);
    $this->actingAsVendor($viewer, $this->tenant)->putJson(($this->url)(), $this->valid)->assertForbidden();
});

it('renders the vendor page with links, push state, features and preview', function (): void {
    $this->actingAsVendor($this->staff, $this->tenant)->get(($this->url)())
        ->assertOk()
        ->assertSee(__('api::panel.title'))
        ->assertSee('data-store-link="ios"', false)
        ->assertSee('data-push-state="missing"', false)
        ->assertSee('data-splash-preview', false)
        ->assertSee(__('api::enums.mobile_feature.image_search'))
        ->assertSee(__('api::panel.states.not_set'))
        ->assertSee(__('navigation.vendor.mobile_app'));
});

it('validates the vendor form', function (): void {
    $this->actingAsVendor($this->staff, $this->tenant)->putJson(($this->url)(), [
        'ios_min_version' => '2.0',
        'ios_latest_version' => '1.9',
        'android_min_version' => 'v1',
        'android_store_url' => 'http://insecure.test/app',
        'splash_background' => 'red',
        'maintenance_message' => ['en' => str_repeat('a', 301)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['ios_latest_version', 'android_min_version', 'android_store_url', 'splash_background', 'maintenance_message.en']);
});

it('saves the vendor settings, the splash logo and feeds the bootstrap', function (): void {
    $this->actingAsVendor($this->staff, $this->tenant)
        ->put(($this->url)(), $this->valid + ['splash_logo' => [UploadedFile::fake()->image('splash.png', 200, 200)]], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $settings = $this->tenant->run(fn () => app(MobileAppSettingsService::class)->get());
    expect($settings->ios->minVersion)->toBe('1.2.0')
        ->and($settings->android->storeUrl)->toBe('https://play.google.com/store/apps/details?id=com.shop')
        ->and($settings->maintenance)->toBeTrue()
        ->and($settings->maintenanceMessage)->toHaveKey('en')->not->toHaveKey('xx')
        ->and($settings->splashBackground)->toBe('#0b0b0b')
        ->and($settings->features)->toMatchArray(['reviews' => true, 'wishlist' => false, 'guest_checkout' => true])
        ->and($settings->splashLogoPath)->toStartWith('mobile-app/');
    $logo = $settings->splashLogoPath;
    $this->tenant->run(fn () => Storage::disk('public')->assertExists($logo));

    $this->getJson(mobileApi('bootstrap'), mobileHeaders($this->tenant, extra: ['X-App-Platform' => 'ios', 'X-App-Version' => '1.1.0']))
        ->assertOk()
        ->assertJsonPath('data.app.update.state', 'required')
        ->assertJsonPath('data.features.wishlist', false);

    // Keeping the tile keeps the logo; dropping it removes the file.
    $this->actingAsVendor($this->staff, $this->tenant)->putJson(($this->url)(), $this->valid + ['splash_logo_keep' => ['current']])->assertOk();
    expect($this->tenant->run(fn () => app(MobileAppSettingsService::class)->get()->splashLogoPath))->toBe($logo);

    $this->actingAsVendor($this->staff, $this->tenant)->putJson(($this->url)(), $this->valid)->assertOk();
    expect($this->tenant->run(fn () => app(MobileAppSettingsService::class)->get()->splashLogoPath))->toBeNull();
    $this->tenant->run(fn () => Storage::disk('public')->assertMissing($logo));

    $this->actingAsVendor($this->staff, $this->tenant)->get(($this->url)())->assertOk()->assertSee(__('api::panel.states.live', ['version' => '1.4.2']));
});

it('lets admins view and edit a store app in the vendor profile tab', function (): void {
    $other = $this->createTenant('mobileother');
    tenancy()->end();

    $viewer = mobileAdmin(['vendors.view']);
    $this->actingAs($viewer, 'admin')->get($this->centralUrl("/admin/vendors/{$this->tenant->id}?tab=mobile-app"))
        ->assertOk()
        ->assertSee('data-admin-mobile-app', false)
        ->assertSee(__('api::panel.push.admin_missing'))
        ->assertDontSee('form="admin-mobile-app-form"', false);
    $this->actingAs($viewer, 'admin')->putJson($this->centralUrl("/admin/vendors/{$this->tenant->id}/mobile-app"), $this->valid)->assertForbidden();

    $manager = mobileAdmin(['vendors.view', 'vendors.manage', 'integrations.view']);
    $this->actingAs($manager, 'admin')->get($this->centralUrl("/admin/vendors/{$this->tenant->id}?tab=mobile-app"))
        ->assertOk()
        ->assertSee('form="admin-mobile-app-form"', false)
        ->assertSee(route('admin.integrations.show', 'firebase'), false);

    $this->actingAs($manager, 'admin')->putJson($this->centralUrl("/admin/vendors/{$this->tenant->id}/mobile-app"), ['ios_latest_version' => 'x'])->assertUnprocessable();
    $this->actingAs($manager, 'admin')->putJson($this->centralUrl("/admin/vendors/{$this->tenant->id}/mobile-app"), $this->valid)->assertOk();

    expect($this->tenant->run(fn () => app(MobileAppSettingsService::class)->get()->ios->latestVersion))->toBe('1.4.2')
        ->and($other->run(fn () => app(MobileAppSettingsService::class)->get()->ios->latestVersion))->toBeNull();
});
