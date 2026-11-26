<?php

use App\Modules\Localization\Models\Country;

require_once __DIR__.'/ReportingHelpers.php';

/*
| Admin › Vendors › Shoppers (ADM-043): access, KPIs, masked rows, filters, CSV export.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
    $this->lina = reportTenant('Lina Boutique');
});

it('redirects guests and forbids admins without the permission', function (): void {
    $this->get(route('admin.vendors.shoppers.index'))->assertRedirect(route('admin.login'));

    $this->actingAs(reportAdmin(['vendors.view']), 'admin');
    $this->get(route('admin.vendors.shoppers.index'))->assertForbidden();
    $this->get(route('admin.vendors.shoppers.data'))->assertForbidden();
    $this->get(route('admin.vendors.shoppers.export'))->assertForbidden();
});

it('shows the page with KPIs and no export link without the export permission', function (): void {
    $this->actingAs(reportAdmin(['vendors.shoppers.view']), 'admin');
    reportFact($this->megz);
    reportFact($this->megz);

    $this->get(route('admin.vendors.shoppers.index'))
        ->assertOk()
        ->assertSee(__('reporting::shoppers.title'))
        ->assertSee(__('reporting::shoppers.kpi.new'))
        ->assertDontSee(route('admin.vendors.shoppers.export'));
});

it('lists one masked row per store shopper with orders, spend and status', function (): void {
    $this->actingAs(reportAdmin(['vendors.shoppers.view']), 'admin');
    reportFact($this->megz, ['customer_id' => 5, 'customer_name' => 'Ahmed Ali Hassan', 'customer_email' => 'ahmed@gmail.com', 'placed_at' => now()->subDays(200)]);
    reportFact($this->megz, ['customer_id' => 5, 'customer_name' => 'Ahmed Ali Hassan', 'customer_email' => 'ahmed@gmail.com', 'placed_at' => now()->subDays(10), 'grand_total' => 2550]);
    reportFact($this->lina, ['customer_id' => 5, 'customer_name' => 'Mona Adel', 'customer_email' => 'mona@yahoo.com', 'placed_at' => now()->subDays(3)]);
    reportFact($this->lina, ['customer_id' => null, 'customer_name' => 'Guest Buyer', 'customer_email' => 'guest@icloud.com', 'placed_at' => now()->subDays(120)]);
    reportFact($this->lina, ['customer_id' => 9, 'status' => 'cancelled']);

    $rows = collect($this->getJson(route('admin.vendors.shoppers.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 3)
        ->json('data'));

    $ahmed = $rows->first(fn (array $row): bool => str_contains($row['shopper'], 'Ahmed H.'));
    expect($ahmed)->not->toBeNull()
        ->and($ahmed['shopper'])->toContain('a•••@gmail.com')->not->toContain('ahmed@gmail.com')->not->toContain('Hassan')
        ->and(strip_tags($ahmed['orders_cell']))->toBe('2')
        ->and(strip_tags($ahmed['spent_cell']))->toBe('$125.50')
        ->and(strip_tags($ahmed['status_cell']))->toContain('Active');

    expect(strip_tags($rows->first(fn (array $row): bool => str_contains($row['shopper'], 'Mona'))['status_cell']))->toContain('New')
        ->and(strip_tags($rows->first(fn (array $row): bool => str_contains($row['shopper'], 'Guest'))['status_cell']))->toContain('Inactive');
});

it('filters shoppers by store, country, status, new only and search', function (array $query, int $expected): void {
    $this->actingAs(reportAdmin(['vendors.shoppers.view']), 'admin');
    $country = Country::factory()->create();
    reportFact($this->megz, ['customer_id' => 1, 'customer_name' => 'Ahmed Ali', 'country_id' => $country->id, 'placed_at' => now()->subDays(5)]);
    reportFact($this->megz, ['customer_id' => 2, 'customer_name' => 'Omar Said', 'placed_at' => now()->subDays(200)]);
    reportFact($this->lina, ['customer_id' => 1, 'customer_name' => 'Fatima Noor', 'placed_at' => now()->subDays(60)]);

    $query = array_map(fn ($value) => $value === '@megz' ? $this->megz->id : ($value === '@country' ? $country->id : $value), $query);

    $this->getJson(route('admin.vendors.shoppers.data', [...$query, 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', $expected);
})->with([
    'store' => [['tenant_id' => '@megz'], 2],
    'country' => [['country_id' => '@country'], 1],
    'status new' => [['status' => 'new'], 1],
    'status active' => [['status' => 'active'], 1],
    'status inactive' => [['status' => 'inactive'], 1],
    'new only' => [['new_only' => 1], 1],
    'search' => [['search' => ['value' => 'Fatima']], 1],
]);

it('rejects an unknown status filter', function (): void {
    $this->actingAs(reportAdmin(['vendors.shoppers.view']), 'admin');

    $this->getJson(route('admin.vendors.shoppers.data', ['status' => 'vip']))->assertUnprocessable();
});

it('exports the filtered shoppers as CSV with masked personal data', function (): void {
    $this->actingAs(reportAdmin(['vendors.shoppers.view', 'vendors.shoppers.export']), 'admin');
    reportFact($this->megz, ['customer_id' => 1, 'customer_name' => 'Ahmed Ali', 'customer_email' => 'ahmed@gmail.com', 'grand_total' => 31240]);
    reportFact($this->lina, ['customer_id' => 4, 'customer_name' => 'Mona Adel', 'customer_email' => 'mona@yahoo.com']);

    $csv = $this->get(route('admin.vendors.shoppers.export', ['tenant_id' => $this->megz->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('"Ahmed A.",a•••@gmail.com,"Megz Store",,1,312.40,New')
        ->and($csv)->not->toContain('ahmed@gmail.com')
        ->and($csv)->not->toContain('Mona');
});
