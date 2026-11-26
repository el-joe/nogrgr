<?php

use App\Modules\Billing\DTOs\Ledger\LedgerLine;
use App\Modules\Billing\DTOs\Ledger\LedgerPostingData;
use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductTenantAssignment;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-009a / 009c / 009d / 009f vendor dashboard widgets: pay-the-platform alert (ledger
| receivable, USD), opportunities + hidden platform promotions to join, new-in central
| products with add to store, live store preview by country — permission-aware and
| isolated per store.
*/

function owePlatform(Tenant $tenant, int $minor, string $number): void
{
    $uuid = (string) Str::uuid();

    app(LedgerService::class)->post(new LedgerPostingData(
        tenantId: $tenant->id,
        source: LedgerSource::Order,
        idempotencyKey: 'test-owe-'.$uuid,
        description: 'Order '.$number,
        lines: [
            LedgerLine::debit(LedgerAccount::VendorReceivable, $minor, $uuid, $number),
            LedgerLine::credit(LedgerAccount::PlatformRevenue, $minor, $uuid, $number),
        ],
        orderUuid: $uuid,
    ));
}

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    [$this->tenant, $this->owner, $this->sa, $this->ae] = catalogStore('dash-'.Str::lower(Str::random(6)));
    [$this->other, $this->otherOwner] = catalogStore('dsho-'.Str::lower(Str::random(6)));
    $this->hot = Product::factory()->create(['name' => ['en' => 'Hot Blender'], 'sold_count' => 90, 'published_at' => now()->subDays(9)]);
    $this->fresh = Product::factory()->create(['name' => ['en' => 'Fresh Kettle'], 'sold_count' => 0, 'published_at' => now()]);
    $this->theirs = Product::factory()->create(['name' => ['en' => 'Their Toaster'], 'sold_count' => 500, 'assignment_scope' => AssignmentScope::Selected]);
    ProductTenantAssignment::query()->create(['product_id' => $this->theirs->id, 'tenant_id' => $this->other->id]);
    $this->url = fn (Tenant $tenant, string $path = 'admin/dashboard') => $this->tenantUrl($tenant, $path);
});

it('shows every widget to the owner with the golden owed amount', function (): void {
    owePlatform($this->tenant, 12345, '1001');
    owePlatform($this->tenant, 655, '1002');
    owePlatform($this->other, 99999, '9001');

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee('$130.00')
        ->assertDontSee('$999.99')
        ->assertSee(trans_choice('platform::dashboard_vendor.pay.orders', 2, ['count' => 2]))
        ->assertSee(vendor_route('finance.purchases.index'), false)
        ->assertSee(__('platform::dashboard_vendor.opportunities.title'))
        ->assertSee('Hot Blender')
        ->assertDontSee('Their Toaster')
        ->assertSee(__('platform::dashboard_vendor.new_in.title'))
        ->assertSee('Fresh Kettle')
        ->assertSee(__('platform::dashboard_vendor.storefront.title'))
        ->assertSee('data-preview-frame', false)
        ->assertSee(__('platform::dashboard_vendor.storefront.links.template'))
        ->assertSee('data-page="vendor-dashboard"', false);
});

it('shows the settled state when nothing is owed', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee(__('platform::dashboard_vendor.pay.settled_title'))
        ->assertDontSee(__('platform::dashboard_vendor.pay.cta'));
});

it('hides every widget from staff without their permissions', function (): void {
    owePlatform($this->tenant, 5000, '1003');
    $staff = catalogStaff($this->tenant, ['dashboard.view']);

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertDontSee('$50.00')
        ->assertDontSee(__('platform::dashboard_vendor.pay.settled_title'))
        ->assertDontSee(__('platform::dashboard_vendor.opportunities.title'))
        ->assertDontSee(__('platform::dashboard_vendor.new_in.title'))
        ->assertDontSee(__('platform::dashboard_vendor.storefront.title'))
        ->assertDontSee('flash-sale-attach-modal', false);
});

it('shows view-only staff the widgets without their actions', function (): void {
    $staff = catalogStaff($this->tenant, ['dashboard.view', 'catalog.products.view', 'flash-sales.view', 'store.themes.view']);
    storeProduct($this->tenant, $this->hot->id);

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee('Hot Blender')
        ->assertSee('Fresh Kettle')
        ->assertDontSee('data-dashboard-import', false)
        ->assertDontSee('data-import-one', false)
        ->assertDontSee('available-for-product', false)
        ->assertDontSee(__('platform::dashboard_vendor.storefront.links.colors'))
        ->assertSee(__('platform::dashboard_vendor.storefront.links.template'));
});

it('opens the flash-sale modal for added opportunities and imports the others', function (): void {
    $added = storeProduct($this->tenant, $this->hot->id);

    $html = $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))->assertOk()->getContent();

    expect($html)->toContain(vendor_route('marketing.flash-sales.available-for-product', ['product' => $added->id]))
        ->toContain(__('platform::dashboard_vendor.opportunities.already'))
        ->toContain('data-id="'.$this->fresh->id.'"');

    $this->actingAsVendor($this->owner, $this->tenant)
        ->get(vendor_route('marketing.flash-sales.available-for-product', ['product' => $added->id]))
        ->assertOk();

    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(vendor_route('catalog.products.new.import'), ['ids' => [$this->fresh->id]])
        ->assertOk()
        ->assertJsonPath('data.imported', 1);

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee(__('platform::dashboard_vendor.new_in.empty_title'));
});

it('lists hidden platform promotions of this store only and joins them', function (): void {
    $sale = FlashSale::factory()->create(['title' => ['en' => 'White Friday Deals']]);
    $coupon = Coupon::factory()->create(['code' => 'NOGRGR20']);
    $hide = fn (Tenant $tenant, $owner, string $path) => $this->actingAsVendor($owner, $tenant)->postJson($this->tenantUrl($tenant, $path), ['hidden' => 1])->assertOk();

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee(__('platform::dashboard_vendor.opportunities.offers_empty'));

    $hide($this->other, $this->otherOwner, "admin/marketing/flash-sales/central/{$sale->id}/visibility");
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))->assertOk()->assertDontSee('White Friday Deals');

    $hide($this->tenant, $this->owner, "admin/marketing/flash-sales/central/{$sale->id}/visibility");
    $hide($this->tenant, $this->owner, "admin/marketing/coupons/central/{$coupon->id}/visibility");

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee('White Friday Deals')
        ->assertSee(__('platform::dashboard_vendor.opportunities.coupon_detail', ['code' => 'NOGRGR20']))
        ->assertSee(vendor_route('marketing.flash-sales.visibility', ['flashSale' => $sale->id]), false);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(vendor_route('marketing.flash-sales.visibility', ['flashSale' => $sale->id]), ['hidden' => 0])
        ->assertOk();

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertDontSee('White Friday Deals')
        ->assertSee('NOGRGR20');
});

it('previews the store per selling country through the signed theme preview', function (): void {
    $response = $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant, 'admin/dashboard?country='.$this->ae->id))->assertOk();
    $html = $response->getContent();

    preg_match('/<script type="application\/json" data-preview-urls>(.*?)<\/script>/s', $html, $match);
    $urls = json_decode($match[1] ?? '{}', true);

    expect($urls)->toHaveKeys(['default', (string) $this->sa->id, (string) $this->ae->id])
        ->and($urls[(string) $this->ae->id])->toContain('country='.$this->ae->id)
        ->and($html)->toMatch('/<option value="'.$this->ae->id.'"\s+selected/');

    $location = $this->get($urls[(string) $this->ae->id])->assertRedirect()->headers->get('Location');
    expect($location)->toContain('tp_country='.$this->ae->id)->toContain('tp_signature=');

    // A country the store does not sell in falls back to the store default.
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant, 'admin/dashboard?country=999999'))
        ->assertOk()
        ->assertSee('<option value="default" selected', false);
});
