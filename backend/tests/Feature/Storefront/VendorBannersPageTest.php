<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\HiddenCentralBanner;
use App\Modules\Storefront\Models\StoreBanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| VEN-035 / 035a Vendor › Your store › Banners: country tabs (selling countries), the
| store's own banners merged with the platform's (read-only, show / hide one or all),
| create / edit with upload, on / off, sort, delete, plan quota, permissions, isolation.
*/

beforeEach(function (): void {
    $this->sa = Country::factory()->iso('SA')->create();
    $this->ae = Country::factory()->iso('AE')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('bnr-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$this->sa->id, $this->ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/banners'.$path);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->data = fn (string $query = ''): array => $this->getJson(($this->url)('/data?draw=1&start=0&length=100'.$query))->assertOk()->json();
    $this->storeBanner = fn (array $attributes = []): StoreBanner => $this->tenant->run(fn (): StoreBanner => StoreBanner::query()->create(array_merge([
        'placement' => 'home.hero', 'title' => ['en' => 'Megz tote bags', 'ar' => 'حقائب'], 'image_path' => 'storefront/banners/own.webp',
        'link_url' => '/c/bags', 'sort_order' => 1, 'is_active' => true,
    ], $attributes)));
});

function vendorBannerPayload(array $overrides = []): array
{
    return array_merge([
        'title' => ['en' => 'Free shipping over $60', 'ar' => 'شحن مجاني'],
        'subtitle' => ['en' => 'This week only', 'ar' => ''],
        'cta_label' => ['en' => 'Shop now', 'ar' => 'تسوق الآن'],
        'placement' => 'home.hero',
        'link_url' => '/c/bags',
        'is_active' => '1',
    ], $overrides);
}

it('sends guests to the login and guards every endpoint with store.banners permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.banners.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('engagement::vendor_banners.subtitle'))->assertDontSee(__('engagement::vendor_banners.add'));
    $this->getJson(($this->url)('/create'))->assertForbidden();
    $this->postJson(($this->url)(), vendorBannerPayload())->assertForbidden();
    $this->putJson(($this->url)('/1'), vendorBannerPayload())->assertForbidden();
    $this->patchJson(($this->url)('/1/active'), ['is_active' => 0])->assertForbidden();
    $this->postJson(($this->url)('/sort'), ['ids' => [1]])->assertForbidden();
    $this->deleteJson(($this->url)('/1'))->assertForbidden();
    $this->patchJson(($this->url)('/central/1/visibility'), ['visible' => 0])->assertForbidden();
    $this->patchJson(($this->url)('/defaults'), ['visible' => 0])->assertForbidden();
});

it('lists the store banners merged with the platform banners per country tab', function (): void {
    $everywhere = Banner::factory()->create(['title' => ['en' => 'White Friday'], 'sort_order' => 1]);
    $egypt = Banner::factory()->create(['title' => ['en' => 'Egypt only'], 'sort_order' => 1]);
    $egypt->countries()->attach($this->eg->id);
    ($this->storeBanner)(['sort_order' => 2]);
    $uae = ($this->storeBanner)(['title' => ['en' => 'UAE launch'], 'sort_order' => 3]);
    $this->tenant->run(fn () => $uae->countryRows()->create(['country_id' => $this->ae->id]));
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('engagement::vendor_banners.show_defaults', ['app' => config('app.name')]))
        ->assertSee($this->sa->name)->assertSee($this->ae->name)->assertDontSee($this->eg->name)
        ->assertSee(trans_choice('engagement::vendor_banners.quota_unlimited', 2, ['used' => 2]))
        // QA-P8-005: quota, limit notice, Add button and country-tab counts are live regions refreshed after changes.
        ->assertSee('data-live="banner-quota"', false)->assertSee('data-live="banner-limit"', false)->assertSee('data-live="banner-add"', false)
        ->assertSee('data-live="tab-count:vendor-banner-country-tabs:'.$this->sa->id.'"', false)
        // QA-P8-006: the always-set placement select is a fixed scope, not an active filter.
        ->assertSee('data-filter-fixed', false);

    // QA-P8: the quota line is pluralised ("1 banner", not "1 banners").
    expect(trans_choice('engagement::vendor_banners.quota_unlimited', 1, ['used' => 1]))->toBe('1 banner · unlimited on your plan')
        ->and(trans_choice('engagement::vendor_banners.quota_unlimited', 2, ['used' => 2]))->toBe('2 banners · unlimited on your plan');

    $sa = ($this->data)('&country_id='.$this->sa->id);
    expect($sa['recordsTotal'])->toBe(2)
        ->and($sa['data'][0]['banner'])->toContain('White Friday')
        ->and($sa['data'][0]['DT_RowAttr']['data-source'])->toBe('central')
        ->and($sa['data'][0]['DT_RowAttr']['data-sort-item'])->toBeNull()
        ->and($sa['data'][0]['status'])->toContain(__('engagement::vendor_banners.admin_badge'))
        ->and($sa['data'][1]['banner'])->toContain('Megz tote bags')->toContain('/c/bags')
        ->and($sa['data'][1]['DT_RowAttr']['data-sort-item'])->toBe('1');

    $ae = ($this->data)('&country_id='.$this->ae->id);
    expect(collect($ae['data'])->pluck('banner')->implode(' '))->toContain('UAE launch')->not->toContain('Egypt only');
    expect(($this->data)('&country_id='.$this->sa->id.'&placement=home.promo')['recordsTotal'])->toBe(0);
    expect(($this->data)('&country_id='.$this->sa->id.'&search=tote')['recordsTotal'])->toBe(1);
});

it('creates a banner with images, edits, switches, sorts and deletes it', function (): void {
    Storage::fake('public');
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)('/create?country='.$this->sa->id), $this->ajax)->assertOk()->assertSee('vendor-banner-form', false);
    $this->post(($this->url)(), vendorBannerPayload(['country_ids' => [$this->eg->id]]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['image', 'country_ids.0']);
    $this->post(($this->url)(), vendorBannerPayload(['link_url' => 'javascript:alert(1)', 'image' => [UploadedFile::fake()->image('b.jpg', 1440, 280)]]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['link_url']);

    $this->post(($this->url)(), vendorBannerPayload([
        'country_ids' => [$this->sa->id],
        'image' => [UploadedFile::fake()->image('banner.jpg', 1440, 280)],
        'mobile_image' => [UploadedFile::fake()->image('mobile.jpg', 720, 360)],
        'dates_from' => now()->toDateString(),
        'dates_to' => now()->addWeek()->toDateString(),
    ]), ['Accept' => 'application/json'])->assertCreated();

    $banner = $this->tenant->run(fn (): StoreBanner => StoreBanner::query()->with('countryRows')->firstOrFail());
    expect($banner->title)->toBe('Free shipping over $60')
        ->and($banner->countryIds())->toBe([$this->sa->id])
        ->and($banner->image_width)->toBe(1440)
        ->and($banner->mobile_image_path)->not->toBeNull();
    $this->tenant->run(fn () => Storage::disk('public')->assertExists($banner->image_path));

    $this->get(($this->url)("/{$banner->id}/edit"), $this->ajax)->assertOk()->assertSee('Free shipping over $60');
    $this->put(($this->url)("/{$banner->id}"), vendorBannerPayload(['title' => ['en' => 'Free shipping over $80', 'ar' => 'شحن مجاني'], 'image_keep' => ['image'], 'mobile_image_keep' => ['mobile']]), ['Accept' => 'application/json'])->assertOk();
    $this->patchJson(($this->url)("/{$banner->id}/active"), ['is_active' => 0])->assertOk();

    $second = ($this->storeBanner)(['sort_order' => 10]);
    $this->postJson(($this->url)('/sort'), ['ids' => [$second->id, $banner->id]])->assertOk();

    $this->tenant->run(function () use ($banner, $second): void {
        $fresh = $banner->fresh();
        expect($fresh->title)->toBe('Free shipping over $80')
            ->and($fresh->is_active)->toBeFalse()
            ->and($fresh->mobile_image_path)->not->toBeNull()
            ->and($second->fresh()->sort_order)->toBeLessThan($fresh->sort_order);
    });

    $this->deleteJson(($this->url)("/{$banner->id}"))->assertOk();
    $this->tenant->run(fn () => expect(StoreBanner::query()->whereKey($banner->id)->exists())->toBeFalse());
});

it('hides and shows one platform banner or all of them on this store only', function (): void {
    $first = Banner::factory()->create(['title' => ['en' => 'White Friday'], 'sort_order' => 1]);
    $second = Banner::factory()->create(['title' => ['en' => 'Ramadan'], 'sort_order' => 2]);
    $other = $this->createTenant('bnr-o-'.Str::lower(Str::random(6)));
    $foreignOnly = Banner::factory()->create(['store_scope' => 'selected']);
    $foreignOnly->tenants()->attach($other->id);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->patchJson(($this->url)("/central/{$first->id}/visibility"), ['visible' => 0])->assertOk();
    $this->tenant->run(fn () => expect(HiddenCentralBanner::query()->pluck('banner_id')->all())->toBe([$first->id]));
    expect(($this->data)('&country_id='.$this->sa->id)['data'][0]['status'])->toContain(__('engagement::vendor_banners.hidden_badge'));

    $this->patchJson(($this->url)("/central/{$foreignOnly->id}/visibility"), ['visible' => 0])->assertNotFound();
    $this->patchJson(($this->url)("/central/{$first->id}/visibility"), [])->assertUnprocessable();

    $this->patchJson(($this->url)('/defaults'), ['visible' => 0])->assertOk();
    $this->tenant->run(fn () => expect(HiddenCentralBanner::query()->pluck('banner_id')->sort()->values()->all())->toBe([$first->id, $second->id]));
    $this->get(($this->url)())->assertOk()->assertDontSee('name="show_defaults" checked', false);

    $this->patchJson(($this->url)('/defaults'), ['visible' => 1])->assertOk();
    $this->tenant->run(fn () => expect(HiddenCentralBanner::query()->count())->toBe(0));
    expect($first->fresh()->is_active)->toBeTrue();
});

it('enforces the plan banner quota on create', function (): void {
    Storage::fake('public');
    $plan = Plan::factory()->create(['limits' => ['banners' => 1]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    ($this->storeBanner)();
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertSee(__('engagement::vendor_banners.limit_reached', ['max' => 1]));
    $this->post(($this->url)(), vendorBannerPayload(['image' => [UploadedFile::fake()->image('b.jpg', 1440, 280)]]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['image']);
    $this->tenant->run(fn () => expect(StoreBanner::query()->count())->toBe(1));
});

it('keeps another store\'s banners out of reach', function (): void {
    $other = $this->createTenant('bnr-x-'.Str::lower(Str::random(6)));
    $foreign = $other->run(fn (): StoreBanner => StoreBanner::query()->forceCreate(['id' => 999, 'placement' => 'home.hero', 'title' => ['en' => 'Foreign banner'], 'image_path' => 'storefront/banners/x.webp', 'sort_order' => 1, 'is_active' => true]));
    $own = ($this->storeBanner)();
    $this->actingAsVendor($this->owner, $this->tenant);

    expect(collect(($this->data)('&country_id='.$this->sa->id)['data'])->pluck('banner')->implode(' '))->not->toContain('Foreign banner');

    expect($own->id)->not->toBe($foreign->id);
    $this->getJson(($this->url)("/{$foreign->id}/edit"))->assertNotFound();
    $this->putJson(($this->url)("/{$foreign->id}"), vendorBannerPayload())->assertNotFound();
    $this->deleteJson(($this->url)("/{$foreign->id}"))->assertNotFound();

    $other->run(fn () => expect(StoreBanner::query()->whereKey($foreign->id)->exists())->toBeTrue());
});
