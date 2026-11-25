<?php

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Services\VendorService;

require_once __DIR__.'/VendorHelpers.php';

/*
| Admin › Vendors › All vendors (ADM-040): permissions, page + tab counts, DataTable
| JSON with filters, CSV export.
*/

it('protects the vendors list, data and export', function (): void {
    $this->get($this->centralUrl('/admin/vendors'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->get($this->centralUrl('/admin/vendors/data'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/vendors/data'))->assertForbidden();
    $this->get($this->centralUrl('/admin/vendors/export'))->assertForbidden();
});

it('keeps view-only admins away from export and create', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');

    $this->get($this->centralUrl('/admin/vendors'))
        ->assertOk()
        ->assertDontSee(route('admin.vendors.create'), false)
        ->assertDontSee(route('admin.vendors.export'), false);
    $this->get($this->centralUrl('/admin/vendors/export'))->assertForbidden();
});

it('renders the list with tabs and counts', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    vendorsTenant(['name' => 'Megz Store']);
    vendorsTenant(['name' => 'Nile Crafts', 'status' => TenantStatus::Suspended]);
    vendorsTenant(['name' => 'Oud & Co', 'status' => TenantStatus::Pending]);

    $this->get($this->centralUrl('/admin/vendors'))
        ->assertOk()
        ->assertSee(__('platform::vendors.tabs.trial'))
        ->assertSee(__('platform::vendors.tabs.suspended'))
        ->assertSee(route('admin.vendors.data'), false)
        ->assertSee(route('admin.vendors.create'), false)
        ->assertSee(route('admin.vendors.export'), false);
});

it('lists vendors as JSON with status, trial, plan, country and search filters', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $saudi = Country::factory()->iso('SA')->create();
    $growth = Plan::factory()->create(['name' => ['en' => 'Growth', 'ar' => 'نمو']]);
    $megz = vendorsTenant(['name' => 'Megz Store', 'owner_email' => 'mostafa@megz.com', 'default_country_id' => $saudi->id, 'profit_percent_bps' => 6250]);
    $oud = vendorsTenant(['name' => 'Oud & Co']);
    vendorsTenant(['name' => 'Nile Crafts', 'status' => TenantStatus::Suspended]);
    Subscription::factory()->for($growth)->create(['tenant_id' => $megz->id]);
    Subscription::factory()->trial(6)->create(['tenant_id' => $oud->id]);

    $url = fn (string $query = '') => $this->centralUrl('/admin/vendors/data?draw=1&start=0&length=10'.$query);

    $all = $this->getJson($url())->assertOk()->json();
    expect($all['recordsTotal'])->toBe(3);

    $row = collect($all['data'])->firstWhere('id', $megz->id);
    expect($row['store'])->toContain('Megz Store')
        ->and($row['owner'])->toContain('mostafa@megz.com')
        ->and($row['plan'])->toContain('Growth')
        ->and($row['profit_percent_bps'])->toContain('62.5%')
        ->and($row['actions'])->toContain(route('admin.vendors.show', $megz->id))
        ->and(array_keys($row))->not->toContain('data')
        ->and(array_keys($row))->not->toContain('provisioning');

    $trialRow = collect($all['data'])->firstWhere('id', $oud->id);
    expect($trialRow['status'])->toContain('d left');

    expect($this->getJson($url('&status=suspended'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&subscription_status=trial'))->json('data.0.id'))->toBe($oud->id)
        ->and($this->getJson($url("&plan_id={$growth->id}"))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url("&country_id={$saudi->id}"))->json('data.0.id'))->toBe($megz->id)
        ->and($this->getJson($url('&search[value]=megz.com'))->json('recordsFiltered'))->toBe(1);
});

it('counts trial stores on the trial tab', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    Subscription::factory()->trial()->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->status(SubscriptionStatus::Active)->create(['tenant_id' => vendorsTenant()->id]);

    expect(app(VendorService::class)->tabCounts())
        ->toMatchArray(['all' => 2, 'active' => 2, 'trial' => 1]);
});

it('exports the filtered vendors as CSV', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.export']), 'admin');
    vendorsTenant(['name' => 'Megz Store', 'profit_percent_bps' => 6500, 'affiliate_commission_bps' => 1000]);
    vendorsTenant(['name' => 'Nile Crafts', 'status' => TenantStatus::Suspended]);

    $response = $this->get($this->centralUrl('/admin/vendors/export?status=active'))->assertOk();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Megz Store')
        ->and($csv)->toContain('65.00')
        ->and($csv)->toContain('10.00')
        ->and($csv)->not->toContain('Nile Crafts');
});
