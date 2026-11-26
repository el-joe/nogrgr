<?php

use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Listeners\RecordProductActivity;
use App\Modules\Reporting\Models\ProductDailyFact;
use App\Modules\Reporting\Services\VendorAnalytics\ClvAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\OrdersAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ProfitabilityAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ReturnsAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\ShippingAnalytics;
use App\Modules\Reporting\Services\VendorAnalytics\VendorReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/ReportingHelpers.php';

/*
| VEN-045 Vendor › Analytics (orders, CLV, shipping, product profitability tabs) and VEN-024
| return analytics: access, golden KPI values from the Reporting facts of the current store
| only, monthly / ranking / product tables, CSV export and isolation from other stores.
*/

beforeEach(function (): void {
    $this->travelTo('2026-11-26 12:00:00');
});

/**
 * KPI raw values of a vendor analytics summary keyed by KPI key.
 *
 * @return array<string, int|float>
 */
function vendorKpis(ReportSummary $summary): array
{
    return collect($summary->kpis)->mapWithKeys(fn (array $kpi): array => [$kpi['key'] => $kpi['raw']])->all();
}

/**
 * @param  class-string<VendorReport>  $report
 */
function vendorSummary(string $report, Tenant $tenant): ReportSummary
{
    return app($report)->summary(new ReportFilterData(tenantId: $tenant->id));
}

/**
 * The store's order facts used by the golden tests (window = the last 30 days).
 */
function seedVendorOrders(Tenant $tenant): void
{
    // A: delivered on time, paid, Aramex.
    reportFact($tenant, ['customer_id' => 1, 'grand_total' => 10000, 'vendor_profit' => 8000, 'placed_at' => now()->subDays(10), 'paid_at' => now()->subDays(10),
        'shipped_at' => now()->subDays(8), 'delivered_at' => now()->subDays(6), 'delivery_max_date' => now()->subDays(5)->toDateString(),
        'shipping_charge' => 500, 'shipping_company_name' => 'Aramex']);
    // B: cash on delivery, shipped, still out past its promised date (delayed), unpaid.
    reportFact($tenant, ['customer_id' => 2, 'customer_name' => 'Sara Omar', 'customer_email' => 'sara@example.com', 'status' => 'shipped', 'payment_status' => 'cod',
        'fulfilment_status' => 'shipped', 'grand_total' => 5000, 'vendor_profit' => 4000, 'placed_at' => now()->subDays(3), 'paid_at' => null,
        'shipped_at' => now()->subDays(2), 'delivery_max_date' => now()->subDay()->toDateString(), 'shipping_charge' => 300, 'shipping_company_name' => 'DHL']);
    // C: cancelled · D: awaiting payment — never booked.
    reportFact($tenant, ['customer_id' => 1, 'status' => 'cancelled', 'payment_status' => 'unpaid', 'grand_total' => 3000, 'placed_at' => now()->subDays(4), 'paid_at' => null]);
    reportFact($tenant, ['customer_id' => 3, 'status' => 'pending_payment', 'payment_status' => 'unpaid', 'grand_total' => 2000, 'placed_at' => now()->subDay(), 'paid_at' => null]);
    // E: an older order of customer 1 (outside the window, part of the lifetime).
    reportFact($tenant, ['customer_id' => 1, 'grand_total' => 4000, 'placed_at' => now()->subDays(100), 'paid_at' => now()->subDays(100)]);
}

function vendorAnalyticsStaff(Tenant $tenant, array $permissions): VendorStaff
{
    return $tenant->run(function () use ($permissions): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', ...$permissions]);

        return $staff;
    });
}

describe('access', function (): void {
    beforeEach(function (): void {
        $this->tenant = $this->createTenant('ana-'.Str::lower(Str::random(6)));
        $this->url = fn (string $path) => $this->tenantUrl($this->tenant, 'admin/'.$path);
    });

    it('redirects guests to the vendor login', function (): void {
        foreach (['analytics', 'analytics/orders/data', 'analytics/orders/export', 'sales/returns/analytics', 'sales/returns/analytics/data', 'sales/returns/analytics/export'] as $path) {
            $this->get(($this->url)($path))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
        }
    });

    it('forbids staff without analytics.view / returns.view', function (): void {
        $this->actingAsVendor(vendorAnalyticsStaff($this->tenant, ['orders.view']), $this->tenant);

        foreach (['analytics', 'analytics/clv/data', 'analytics/shipping/export', 'sales/returns/analytics', 'sales/returns/analytics/data', 'sales/returns/analytics/export'] as $path) {
            $this->get(($this->url)($path))->assertForbidden();
        }
    });

    it('lets analytics.view open every tab, but not return analytics', function (): void {
        $this->actingAsVendor(vendorAnalyticsStaff($this->tenant, ['analytics.view']), $this->tenant);

        foreach (['orders', 'clv', 'shipping', 'profitability'] as $tab) {
            $this->get(($this->url)('analytics?tab='.$tab))->assertOk()
                ->assertSee(__('reporting::vendor_analytics.tabs.'.$tab))
                ->assertSee(__('reporting::vendor_analytics.empty_title'));
        }

        $this->get(($this->url)('analytics/unknown/data'))->assertNotFound();
        $this->get(($this->url)('analytics/returns/data'))->assertNotFound();
        $this->get(($this->url)('sales/returns/analytics'))->assertForbidden();
    });

    it('rejects an invalid period', function (): void {
        $this->actingAsVendor($this->createVendorOwner($this->tenant, ['email' => 'own-'.Str::lower(Str::random(6)).'@megz.test']), $this->tenant);

        $this->get(($this->url)('analytics?from=2026-11-20&to=2026-11-01'))->assertSessionHasErrors('to');
    });
});

describe('pages, tables and exports of the current store', function (): void {
    beforeEach(function (): void {
        $this->tenant = $this->createTenant('anb-'.Str::lower(Str::random(6)));
        $this->other = reportTenant('Lina Boutique');
        $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'own-'.Str::lower(Str::random(6)).'@megz.test']);
        $this->url = fn (string $path) => $this->tenantUrl($this->tenant, 'admin/'.$path);
        seedVendorOrders($this->tenant);
        reportFact($this->other, ['customer_id' => 1, 'customer_name' => 'Other Shopper', 'grand_total' => 99900, 'placed_at' => now()->subDays(2)]);
    });

    it('renders the orders tab with KPIs of this store only', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);

        $this->get(($this->url)('analytics'))->assertOk()
            ->assertSee(__('reporting::vendor_analytics.kpi.gross'))
            ->assertSee('$150.00')
            ->assertSee('$100.00')
            ->assertSee('$75.00')
            ->assertDontSee('$999.00')
            ->assertSee(__('reporting::vendor_analytics.funnel_title'))
            ->assertSee(vendor_route('analytics.export', ['tab' => 'orders', 'from' => '2026-10-28', 'to' => '2026-11-26'], false));
    });

    it('serves the monthly performance table', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);

        $rows = $this->getJson(($this->url)('analytics/orders/data?draw=1&start=0&length=10'))->assertOk()->json('data');

        expect($rows)->toHaveCount(1)
            ->and(strip_tags($rows[0]['c_orders']))->toBe('3')
            ->and(strip_tags($rows[0]['c_paid']))->toBe('1')
            ->and(strip_tags($rows[0]['c_gross']))->toBe('$150.00')
            ->and(strip_tags($rows[0]['c_collected']))->toBe('$100.00')
            ->and(strip_tags($rows[0]['c_avg_order']))->toBe('$75.00');
    });

    it('ranks the store customers by lifetime spend without other stores', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);

        $query = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['data' => 'c_spent', 'name' => 'spent', 'orderable' => 'true', 'searchable' => 'false']]]);
        $rows = $this->getJson(($this->url)('analytics/clv/data?'.$query))->assertOk()->json('data');

        expect($rows)->toHaveCount(2)
            ->and(strip_tags($rows[0]['c_customer_name']))->toContain('Ahmed Ali')
            ->and(strip_tags($rows[0]['c_spent']))->toBe('$140.00')
            ->and(strip_tags($rows[0]['c_orders']))->toBe('2')
            ->and(strip_tags($rows[1]['c_spent']))->toBe('$50.00')
            ->and(json_encode($rows))->not->toContain('Other Shopper');
    });

    it('exports the tab as CSV', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);

        $response = $this->get(($this->url)('analytics/shipping/export'))->assertOk();
        $csv = $response->streamedContent();

        expect($response->headers->get('content-disposition'))->toContain('analytics-shipping-2026-10-28-2026-11-26.csv')
            ->and($csv)->toContain(__('reporting::vendor_analytics.columns.carrier'))
            ->and($csv)->toContain('Aramex')
            ->and($csv)->toContain('DHL');
    });

    it('renders return analytics with its refunds table and export', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);
        seedVendorReturns($this->tenant, $this->other);

        $this->get(($this->url)('sales/returns/analytics'))->assertOk()
            ->assertSee(__('reporting::vendor_analytics.returns_title'))
            ->assertSee('$25.00');

        $rows = $this->getJson(($this->url)('sales/returns/analytics/data?draw=1&start=0&length=10'))->assertOk()->json('data');
        expect($rows)->toHaveCount(2)->and(json_encode($rows))->toContain('RF-A1')->not->toContain('RF-OTHER');

        $csv = $this->get(($this->url)('sales/returns/analytics/export'))->assertOk()->streamedContent();
        expect($csv)->toContain('RF-A1')->toContain('25.00')->not->toContain('RF-OTHER');
    });
});

/**
 * Two return requests (return refunded after 2 days, exchange done after 3 days), a
 * completed and a failed refund of $tenant, and one return + refund of $other.
 */
function seedVendorReturns(Tenant $tenant, Tenant $other): void
{
    $return = fn (Tenant $store, string $reference, string $type, array $extra) => DB::table('return_requests')->insertGetId(array_merge([
        'reference' => $reference, 'tenant_id' => $store->id, 'order_uuid' => (string) Str::uuid(), 'order_number' => 'NG-'.$reference,
        'order_item_id' => 1, 'product_name' => 'Linen Dress', 'quantity' => 1, 'type' => $type, 'reason_code' => 'damaged',
        'description' => 'Torn seam', 'status' => 'closed', 'created_by_type' => 'customer', 'updated_at' => now(),
    ], $extra));
    $refund = fn (Tenant $store, string $reference, ?int $returnId, string $status, int $amount, array $extra) => DB::table('refunds')->insert(array_merge([
        'reference' => $reference, 'tenant_id' => $store->id, 'order_uuid' => (string) Str::uuid(), 'order_number' => 'NG-'.$reference,
        'return_request_id' => $returnId, 'source' => $returnId ? 'return' : 'manual', 'reason' => 'Damaged item', 'amount' => $amount,
        'method' => 'original', 'status' => $status, 'requested_by_type' => 'vendor', 'created_at' => now(), 'updated_at' => now(),
    ], $extra));

    $r1 = $return($tenant, 'RMA-A1', 'return', ['created_at' => now()->subDays(5), 'refunded_at' => now()->subDays(3)]);
    $return($tenant, 'RMA-A2', 'exchange', ['created_at' => now()->subDays(4), 'exchanged_at' => now()->subDay()]);
    $r3 = $return($other, 'RMA-OTHER', 'exchange', ['created_at' => now()->subDays(4), 'exchanged_at' => now()->subDays(3)]);

    $refund($tenant, 'RF-A1', $r1, 'completed', 2500, ['requested_at' => now()->subDays(3), 'processed_at' => now()->subDays(3)]);
    $refund($tenant, 'RF-A2', null, 'failed', 1500, ['requested_at' => now()->subDays(2)]);
    $refund($other, 'RF-OTHER', $r3, 'completed', 77700, ['requested_at' => now()->subDays(3), 'processed_at' => now()->subDays(3)]);
}

describe('golden KPI values', function (): void {
    beforeEach(function (): void {
        $this->tenant = reportTenant('Megz Store');
        $this->other = reportTenant('Lina Boutique');
        seedVendorOrders($this->tenant);
        seedVendorOrders($this->other);
        reportFact($this->other, ['customer_id' => 9, 'grand_total' => 50000, 'placed_at' => now()->subDays(2)]);
    });

    it('computes gross, collected, outstanding and the average order', function (): void {
        $summary = vendorSummary(OrdersAnalytics::class, $this->tenant);

        expect(vendorKpis($summary))->toBe(['gross' => 15000, 'collected' => 10000, 'outstanding' => 5000, 'avg_order' => 7500])
            ->and(collect($summary->funnel)->pluck('percent')->sum())->toEqualWithDelta(100.0, 0.2)
            ->and(collect($summary->charts)->firstWhere('id', 'status_mix')['total'])->toBe('3');
    });

    it('computes customers, buyers, repeat buyers, lifetime spend and the buyer mix', function (): void {
        $summary = vendorSummary(ClvAnalytics::class, $this->tenant);
        $mix = collect($summary->charts)->firstWhere('id', 'buyer_mix');

        expect(vendorKpis($summary))->toBe(['customers' => 2, 'buyers' => 2, 'repeat_buyers' => 1, 'avg_lifetime' => 9500])
            ->and(array_combine($mix['labels'], $mix['series'][0]['data']))->toBe([
                __('reporting::vendor_analytics.mix.new') => 1,
                __('reporting::vendor_analytics.mix.repeat') => 1,
            ])
            ->and($summary->leaders[0]['value'])->toBe('$140.00')
            ->and($summary->leaders[1]['percent'])->toBe(35.7);
    });

    it('computes on-time rate, delivery days, shipping cost per order and delayed orders', function (): void {
        $summary = vendorSummary(ShippingAnalytics::class, $this->tenant);
        $carriers = collect($summary->charts)->firstWhere('id', 'deliveries_by_company');

        expect(vendorKpis($summary))->toBe(['on_time_rate' => 100.0, 'avg_delivery_days' => 2.0, 'shipping_per_order' => 400, 'delayed' => 1])
            ->and(array_combine($carriers['labels'], $carriers['series'][0]['data']))->toEqualCanonicalizing(['Aramex' => 1, 'DHL' => 1]);
    });

    it('computes revenue, profit, margin and units per product from paid lines', function (): void {
        $day = fn (Tenant $store, int $product, int $daysAgo, array $values) => DB::table('product_daily_facts')->insert(array_merge([
            'tenant_id' => $store->id, 'store_product_id' => $product, 'day' => now()->subDays($daysAgo)->toDateString(), 'product_name' => 'Product '.$product,
            'views' => 0, 'cart_adds' => 0, 'orders' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], $values));
        $day($this->tenant, 1, 5, ['units' => 3, 'revenue' => 9000, 'profit' => 2700]);
        $day($this->tenant, 1, 2, ['units' => 1, 'revenue' => 3000, 'profit' => 900]);
        $day($this->tenant, 2, 2, ['units' => 2, 'revenue' => 4000, 'profit' => 400]);
        $day($this->tenant, 2, 60, ['units' => 9, 'revenue' => 90000, 'profit' => 9000]);
        $day($this->other, 1, 2, ['units' => 50, 'revenue' => 500000, 'profit' => 100000]);

        $filters = new ReportFilterData(tenantId: $this->tenant->id);
        $report = app(ProfitabilityAnalytics::class);
        $rows = $report->rows($filters)->orderByDesc('profit')->get();

        expect(vendorKpis($report->summary($filters)))->toBe(['revenue' => 16000, 'profit' => 4000, 'margin' => 25.0, 'units' => 6])
            ->and($rows->pluck('profit', 'store_product_id')->map(fn ($v) => (int) $v)->all())->toBe([1 => 3600, 2 => 400])
            ->and((float) $rows[0]->margin)->toBe(30.0)
            ->and((float) $rows[1]->margin)->toBe(10.0);
    });

    it('computes return rate, refunded, exchanges and the average resolution', function (): void {
        seedVendorReturns($this->tenant, $this->other);

        $summary = vendorSummary(ReturnsAnalytics::class, $this->tenant);

        expect(vendorKpis($summary))->toBe(['return_rate' => 100.0, 'refunded' => 2500, 'exchanges' => 1, 'avg_resolution_days' => 2.5])
            ->and(collect($summary->charts)->firstWhere('id', 'return_reasons')['total'])->toBe('2');
    });

    it('records the store profit of each paid line into the product facts', function (): void {
        $order = new Order(['uuid' => (string) Str::uuid()]);
        $order->paid_at = now();
        $order->setRelation('items', collect([
            new OrderItem(['store_product_id' => 31, 'name' => 'Linen Dress', 'quantity' => 2, 'cost' => 1000, 'profit_amount' => 500, 'grand_total' => 3000]),
            new OrderItem(['store_product_id' => 31, 'name' => 'Linen Dress', 'quantity' => 1, 'cost' => 1000, 'profit_amount' => 400, 'discount' => 100, 'discount_source' => 'vendor', 'grand_total' => 1300]),
        ]));

        app(RecordProductActivity::class)->handle(new OrderPaid($order, $this->tenant->id));

        $fact = ProductDailyFact::withoutTenantScope()->where('tenant_id', $this->tenant->id)->sole();
        expect($fact->revenue)->toBe(4300)->and($fact->profit)->toBe(1300)->and($fact->units)->toBe(3);
    });

    it('requires the store on every query', function (): void {
        app(OrdersAnalytics::class)->summary(new ReportFilterData);
    })->throws(InvalidArgumentException::class);
});
