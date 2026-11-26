<?php

use App\Modules\Localization\Models\Country;

require_once __DIR__.'/../Reporting/ReportingHelpers.php';

/*
| Admin dashboard (ADM-006): KPIs, recent orders, top vendors and links from Reporting facts.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
    $this->lina = reportTenant('Lina Boutique');
});

it('shows live KPIs, recent orders, top vendors and the report links', function (): void {
    $this->actingAs(reportAdmin(), 'admin');
    reportFact($this->megz, ['order_number' => 'NG-10431', 'grand_total' => 42000, 'platform_profit' => 9000, 'placed_at' => now()->startOfDay()->addMinutes(5)]);
    reportFact($this->lina, ['order_number' => 'NG-10430', 'grand_total' => 11800, 'platform_profit' => 2000, 'placed_at' => now()->startOfDay()->addMinute()]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('$538.00')
        ->assertSee('$110.00')
        ->assertSee('#NG-10431')
        ->assertSee('Megz Store')
        ->assertSee(__('platform::dashboard.leaderboard'))
        ->assertSee(route('admin.reports.show', ['tab' => 'vendors', 'from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString()]))
        ->assertSee(route('admin.reports.export', ['tab' => 'sales', 'from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString()]))
        ->assertSee(__('platform::dashboard.all_countries'))
        ->assertDontSee(__('platform::dashboard.recent_orders.empty_title'));
});

it('narrows the commerce figures to one country', function (): void {
    $this->actingAs(reportAdmin(), 'admin');
    $country = Country::factory()->create();
    reportFact($this->megz, ['order_number' => 'NG-20001', 'grand_total' => 42000, 'country_id' => $country->id]);
    reportFact($this->lina, ['order_number' => 'NG-20002', 'grand_total' => 11800]);

    $this->get(route('admin.dashboard', ['country_id' => $country->id]))
        ->assertOk()
        ->assertSee('#NG-20001')
        ->assertDontSee('#NG-20002')
        ->assertSee('$420.00');
});

it('shows the empty states and hides report links without permissions', function (): void {
    $this->actingAs(reportAdmin([]), 'admin');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('platform::dashboard.recent_orders.empty_title'))
        ->assertSee(__('platform::dashboard.top_vendors.empty_title'))
        ->assertDontSee(__('platform::dashboard.export_report'))
        ->assertDontSee(__('platform::dashboard.leaderboard'));
});

it('validates the dashboard filters', function (): void {
    $this->actingAs(reportAdmin(), 'admin');

    $this->get(route('admin.dashboard', ['country_id' => 999999]))->assertSessionHasErrors('country_id');
});
