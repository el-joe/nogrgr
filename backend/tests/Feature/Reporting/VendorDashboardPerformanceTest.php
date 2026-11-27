<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Reporting\Enums\DashboardPeriod;
use App\Modules\Reporting\Services\VendorDashboardPerformanceService;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

require_once __DIR__.'/ReportingHelpers.php';
require_once __DIR__.'/../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-009 vendor dashboard "Your store's performance" (client frame 12:11267): KPIs with
| deltas vs the previous window, period selector, gross vs collected, status mix, latest
| orders — USD from Reporting facts of the current store only, display conversion only for
| a synced currency; analytics.view gates the block, orders.view the order links.
*/

beforeEach(function (): void {
    $this->travelTo('2026-11-26 12:00:00');
});

function performanceKpis(object $data): array
{
    return collect($data->kpis)->mapWithKeys(fn (array $kpi): array => [$kpi['key'] => $kpi])->all();
}

it('computes golden KPI values for the period vs the previous window', function (): void {
    $tenant = reportTenant();
    $other = reportTenant('Other');
    // Current 30-day window: 3 booked orders (2 paid 100.00 + 50.00, 1 COD unpaid 30.00), 1 cancelled, 1 awaiting payment.
    reportFact($tenant, ['grand_total' => 10000, 'customer_id' => 1, 'placed_at' => now()->subDays(3)]);
    reportFact($tenant, ['grand_total' => 5000, 'customer_id' => 2, 'status' => 'processing', 'placed_at' => now()->subDays(5)]);
    reportFact($tenant, ['grand_total' => 3000, 'customer_id' => 2, 'status' => 'processing', 'payment_status' => 'pending', 'paid_at' => null, 'placed_at' => now()->subDays(6)]);
    reportFact($tenant, ['grand_total' => 9900, 'status' => 'cancelled', 'placed_at' => now()->subDays(4)]);
    reportFact($tenant, ['grand_total' => 7700, 'status' => 'pending_payment', 'paid_at' => null, 'placed_at' => now()->subDays(4)]);
    // Previous window: 1 booked order of 60.00.
    reportFact($tenant, ['grand_total' => 6000, 'customer_id' => 3, 'placed_at' => now()->subDays(40)]);
    // Another store never counts.
    reportFact($other, ['grand_total' => 999900, 'placed_at' => now()->subDays(2)]);

    $data = app(VendorDashboardPerformanceService::class)->summary($tenant->id, DashboardPeriod::Days30);
    $kpis = performanceKpis($data);

    expect($kpis['orders']['raw'])->toBe(3)
        ->and($kpis['orders']['delta'])->toBe(200.0)
        ->and($kpis['average']['raw'])->toBe(6000)
        ->and($kpis['average']['value'])->toBe('$60.00')
        ->and($kpis['average']['delta'])->toBe(0.0)
        ->and($kpis['outstanding']['raw'])->toBe(3000)
        ->and($kpis['outstanding']['value'])->toBe('$30.00')
        ->and($kpis['customers']['raw'])->toBe(2)
        ->and($kpis['outstanding']['converted'])->toBeNull()
        ->and($data->placedTotal)->toBe(4)
        ->and(collect($data->statusMix)->pluck('count', 'status')->all())->toMatchArray(['processing' => 2, 'delivered' => 1, 'cancelled' => 1])
        ->and(array_sum($data->grossChart['series'][0]['data']))->toBe(180.0)
        ->and(array_sum($data->grossChart['series'][1]['data']))->toBe(150.0)
        ->and($data->latestOrders)->toHaveCount(5)
        ->and(collect($data->latestOrders)->pluck('total')->all())->not->toContain('$9,999.00');
});

it('builds the period windows ending today', function (): void {
    $today = CarbonImmutable::parse('2026-11-26');

    expect(DashboardPeriod::Days7->filters(1, $today)->from)->toBe('2026-11-20')
        ->and(DashboardPeriod::Days30->filters(1, $today)->from)->toBe('2026-10-28')
        ->and(DashboardPeriod::Months12->filters(1, $today)->from)->toBe('2025-12-01')
        ->and(DashboardPeriod::fromQuery('nope'))->toBe(DashboardPeriod::Months12)
        ->and(DashboardPeriod::fromQuery('7d'))->toBe(DashboardPeriod::Days7);
});

it('adds the display conversion only for a synced currency of the chosen country', function (): void {
    $tenant = reportTenant();
    reportFact($tenant, ['grand_total' => 10000, 'payment_status' => 'pending', 'paid_at' => null, 'placed_at' => now()->subDays(2)]);
    $code = 'Q'.Str::upper(Str::random(2));
    $currency = Currency::factory()->create(['code' => $code, 'symbol' => $code, 'decimals' => 2, 'rate' => '3.750000', 'is_active' => true, 'is_base' => false, 'rate_synced_at' => null]);
    $country = Country::factory()->create(['currency_code' => $code, 'is_active' => true]);
    $service = app(VendorDashboardPerformanceService::class);

    expect(performanceKpis($service->summary($tenant->id, DashboardPeriod::Days30, $country->id))['outstanding']['converted'])->toBeNull();

    $currency->forceFill(['rate_synced_at' => now()])->save();
    $converted = performanceKpis($service->summary($tenant->id, DashboardPeriod::Days30, $country->id))['outstanding']['converted'];

    expect($converted)->toContain('375.00');
});

it('shows the block on the dashboard for owners and hides it without analytics.view', function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    [$tenant, $owner] = catalogStore('perf-'.Str::lower(Str::random(6)));
    $fact = reportFact($tenant, ['order_number' => 'NG-PERF1', 'placed_at' => now()->subDays(1)]);
    $url = $this->tenantUrl($tenant, 'admin/dashboard?period=7d');

    $this->actingAsVendor($owner, $tenant)->get($url)
        ->assertOk()
        ->assertSee(__('reporting::vendor_dashboard.title'))
        ->assertSee(__('reporting::vendor_dashboard.periods.7d'))
        ->assertSee('NG-PERF1')
        ->assertSee($fact->order_uuid, false)
        ->assertSee('data-kpi="orders"', false);

    $viewer = catalogStaff($tenant, ['analytics.view']);
    $this->actingAsVendor($viewer, $tenant)->get($url)
        ->assertOk()
        ->assertSee('NG-PERF1')
        ->assertDontSee($fact->order_uuid, false);

    $plain = catalogStaff($tenant, []);
    $this->actingAsVendor($plain, $tenant)->get($url)
        ->assertOk()
        ->assertDontSee(__('reporting::vendor_dashboard.title'));
});

it('shows the empty states when the store has no orders', function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    [$tenant, $owner] = catalogStore('perf-'.Str::lower(Str::random(6)));

    $this->actingAsVendor($owner, $tenant)->get($this->tenantUrl($tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertSee(__('reporting::vendor_dashboard.latest.empty_title'))
        ->assertSee(__('reporting::vendor_dashboard.empty_title'));
});
