<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Reporting\Enums\ReportTab;

require_once __DIR__.'/ReportingHelpers.php';

/*
| Admin › Reports (ADM-070 / 070a…070k): access, validation, every tab renders with and
| without data, table JSON, CSV export, store / country isolation.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
    $this->lina = reportTenant('Lina Boutique');
});

it('redirects guests to the admin login', function (): void {
    $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.reports.show', 'sales'))->assertRedirect(route('admin.login'));
});

it('forbids admins without the reports permission', function (): void {
    $this->actingAs(reportAdmin([]), 'admin');

    $this->get(route('admin.reports.show', 'sales'))->assertForbidden();
    $this->get(route('admin.reports.data', 'sales'))->assertForbidden();
    $this->get(route('admin.reports.export', 'sales'))->assertForbidden();
});

it('needs the export permission to download a report', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->get(route('admin.reports.show', 'sales'))->assertOk()->assertDontSee(route('admin.reports.export', 'sales'));
    $this->get(route('admin.reports.export', 'sales'))->assertForbidden();
});

it('redirects the hub to the sales tab keeping the filters', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->get(route('admin.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-20']))
        ->assertRedirect(route('admin.reports.show', ['tab' => 'sales', 'from' => '2026-11-01', 'to' => '2026-11-20']));
});

it('validates the filters', function (array $query): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->getJson(route('admin.reports.data', ['tab' => 'sales', ...$query]))->assertUnprocessable();
})->with([
    'bad date' => [['from' => '26-11-2026']],
    'to before from' => [['from' => '2026-11-10', 'to' => '2026-11-01']],
    'unknown store' => [['tenant_id' => 999999]],
    'unknown country' => [['country_id' => 999999]],
    'window too long' => [['from' => '2020-01-01', 'to' => '2026-11-01']],
]);

it('returns 404 for an unknown tab', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->get(route('admin.reports.index').'/ai-usage')->assertNotFound();
});

it('renders every tab empty, with its empty state', function (ReportTab $tab): void {
    $this->actingAs(reportAdmin(['reports.view', 'reports.export']), 'admin');

    $this->get(route('admin.reports.show', $tab->value))
        ->assertOk()
        ->assertSee(__('reporting::reports.tabs.'.$tab->value))
        ->assertSee(__('reporting::reports.empty_title'))
        ->assertSee(route('admin.reports.export', ['tab' => $tab->value, 'from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString()]));

    $this->getJson(route('admin.reports.data', ['tab' => $tab->value, 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 0);
})->with(fn (): array => array_map(static fn (ReportTab $tab): array => [$tab], ReportTab::cases()));

it('renders every tab with data', function (ReportTab $tab): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    reportFact($this->megz, ['coupon_discount' => 500, 'coupon_code' => 'NOGRGR10', 'coupon_source' => 'central', 'shipped_at' => now()->subDays(2), 'delivered_at' => now()->subDay(), 'delivery_max_date' => now()->toDateString(), 'shipping_company_name' => 'Aramex']);
    reportFact($this->lina, ['grand_total' => 5000, 'platform_profit' => 1000, 'vendor_profit' => 4000, 'customer_id' => 7, 'status' => 'cancelled']);

    $this->get(route('admin.reports.show', $tab->value))->assertOk()->assertSee(__('reporting::reports.tables.'.$tab->value));
    $this->getJson(route('admin.reports.data', ['tab' => $tab->value, 'draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
})->with(fn (): array => array_map(static fn (ReportTab $tab): array => [$tab], ReportTab::cases()));

it('aggregates sales per store from booked orders only', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    reportFact($this->megz);
    reportFact($this->megz, ['grand_total' => 5050, 'items_count' => 1]);
    reportFact($this->megz, ['status' => 'cancelled', 'grand_total' => 99999]);
    reportFact($this->megz, ['status' => 'pending_payment', 'grand_total' => 99999]);
    reportFact($this->lina, ['grand_total' => 2500]);
    reportFact($this->lina, ['placed_at' => now()->subDays(60), 'grand_total' => 77777]);

    $this->get(route('admin.reports.show', 'sales'))
        ->assertOk()
        ->assertSee('$175.50')
        ->assertDontSee(__('reporting::reports.empty_title'));

    $rows = $this->getJson(route('admin.reports.data', ['tab' => 'sales', 'draw' => 1, 'start' => 0, 'length' => 10, 'order' => [['column' => 2, 'dir' => 'desc']], 'columns' => reportColumns(['tenant_name', 'orders', 'gross', 'refunded', 'net', 'share'])]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 2)
        ->json('data');

    expect(strip_tags($rows[0]['c_tenant_name']))->toContain('Megz Store')
        ->and(strip_tags($rows[0]['c_gross']))->toBe('$150.50')
        ->and(strip_tags($rows[0]['c_orders']))->toBe('2')
        ->and(strip_tags($rows[1]['c_gross']))->toBe('$25.00');
});

it('narrows a report to one store', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    reportFact($this->megz);
    reportFact($this->lina, ['grand_total' => 2500]);

    $rows = $this->getJson(route('admin.reports.data', ['tab' => 'sales', 'tenant_id' => $this->lina->id, 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->json('data');

    expect(strip_tags($rows[0]['c_tenant_name']))->toContain('Lina Boutique');
});

it('narrows a report to one country', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    $country = Country::factory()->create();
    reportFact($this->megz, ['country_id' => $country->id]);
    reportFact($this->lina);

    $this->getJson(route('admin.reports.data', ['tab' => 'sales', 'country_id' => $country->id, 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);
});

it('exports the report as CSV with raw USD amounts', function (): void {
    $this->actingAs(reportAdmin(['reports.view', 'reports.export']), 'admin');
    reportFact($this->megz, ['grand_total' => 12345]);

    $response = $this->get(route('admin.reports.export', 'sales'));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Store,Orders,Gross,Refunds,Net,Share')
        ->and($csv)->toContain('"Megz Store",1,123.45,0.00,123.45,100.0');
});

it('exports the monthly orders series per store', function (): void {
    $this->actingAs(reportAdmin(['reports.view', 'reports.export']), 'admin');
    reportFact($this->megz, ['placed_at' => now()->startOfMonth()->addHour()]);
    reportFact($this->megz, ['placed_at' => now()->startOfMonth()->addHours(2), 'status' => 'cancelled']);

    $csv = $this->get(route('admin.reports.export', 'orders'))->streamedContent();

    expect($csv)->toContain(now()->format('Y-m').',"Megz Store",2,1,1,2,100.00');
});

/**
 * DataTables column parameters for the given report column keys.
 *
 * @param  list<string>  $keys
 * @return list<array<string, mixed>>
 */
function reportColumns(array $keys): array
{
    return array_map(static fn (string $key): array => ['data' => 'c_'.$key, 'name' => $key, 'orderable' => 'true', 'searchable' => 'false', 'search' => ['value' => '']], $keys);
}
