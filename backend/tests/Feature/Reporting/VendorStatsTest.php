<?php

require_once __DIR__.'/ReportingHelpers.php';

/*
| Vendor profile › Stats & orders (ADM-041e): tab, KPIs, orders table, store isolation.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
    $this->lina = reportTenant('Lina Boutique');
});

it('shows the stats tab with the store figures', function (): void {
    $this->actingAs(reportAdmin(['vendors.view']), 'admin');
    reportFact($this->megz, ['grand_total' => 4004]);
    reportFact($this->megz, ['grand_total' => 4004, 'customer_id' => 2]);
    reportFact($this->lina, ['grand_total' => 99900]);

    $this->get(route('admin.vendors.show', ['tenant' => $this->megz->id, 'tab' => 'stats']))
        ->assertOk()
        ->assertSee(__('platform::vendors.profile.tabs.stats'))
        ->assertSee(__('reporting::vendor_stats.chart_title'))
        ->assertSee('$40.04')
        ->assertSee('$80.08')
        ->assertDontSee('$999.00')
        ->assertSee(route('admin.vendors.stats.orders', $this->megz->id));
});

it('lists only the store\'s placed orders, filtered by status and search', function (): void {
    $this->actingAs(reportAdmin(['vendors.view']), 'admin');
    reportFact($this->megz, ['order_number' => 'NG-AAA111', 'status' => 'delivered']);
    reportFact($this->megz, ['order_number' => 'NG-BBB222', 'status' => 'cancelled']);
    reportFact($this->megz, ['order_number' => 'NG-CCC333', 'status' => 'pending_payment']);
    reportFact($this->lina, ['order_number' => 'NG-DDD444']);

    $url = fn (array $query = []) => route('admin.vendors.stats.orders', ['tenant' => $this->megz->id, 'draw' => 1, 'start' => 0, 'length' => 10, ...$query]);

    $this->getJson($url())->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->getJson($url(['status' => 'cancelled']))->assertOk()->assertJsonPath('recordsFiltered', 1);
    $rows = $this->getJson($url(['search' => ['value' => 'AAA']]))->assertOk()->assertJsonPath('recordsFiltered', 1)->json('data');

    expect($rows[0]['order'])->toContain('NG-AAA111');
});

it('protects the orders table with the vendors permission', function (): void {
    $this->get(route('admin.vendors.stats.orders', $this->megz->id))->assertRedirect(route('admin.login'));

    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    $this->getJson(route('admin.vendors.stats.orders', $this->megz->id))->assertForbidden();
});
