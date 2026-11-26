<?php

use App\Modules\Billing\Enums\AddonPurchaseStatus;
use App\Modules\Billing\Enums\AddonType;
use App\Modules\Billing\Models\AddonPurchase;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Models\DomainRequest;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/VendorHelpers.php';

/*
| Admin › Vendors › Vendor profile (ADM-041…041f): tabs, edit store, profit & commission.
*/

it('protects the profile endpoints', function (): void {
    $tenant = vendorsTenant();

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}"))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin([]), 'admin');
    $this->getJson($this->centralUrl("/admin/vendors/{$tenant->id}"))->assertForbidden();

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}"), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}/commission"), [])->assertForbidden();
});

it('renders only the built tabs and hides manage buttons from viewers', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $tenant = vendorsTenant(['name' => 'Megz Store']);

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}"))
        ->assertOk()
        ->assertSee('Megz Store')
        ->assertSee($tenant->subdomainHost())
        ->assertSee(__('platform::vendors.profile.tabs.plan'))
        ->assertSee(__('platform::vendors.profile.tabs.compliance'))
        ->assertSee(__('platform::vendors.profile.tabs.stats'))
        ->assertDontSee('Mobile app')
        ->assertDontSee('>Limits<', false)
        ->assertDontSee(__('platform::vendors.profile.edit_store'))
        ->assertDontSee('id="impersonate"', false);
});

it('shows the overview with store info, owner and health', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $saudi = Country::factory()->iso('SA')->create(['name' => 'Saudi Arabia']);
    $tenant = vendorsTenant(['name' => 'Megz Store', 'owner_name' => 'Mostafa Gamal', 'default_country_id' => $saudi->id, 'selling_country_ids' => [$saudi->id]]);
    DomainRequest::factory()->create(['tenant_id' => $tenant->id, 'host' => 'megz.sa', 'status' => DomainRequestStatus::Connected]);
    ComplianceProfile::factory()->create(['tenant_id' => $tenant->id, 'status' => ComplianceStatus::Verified]);
    AddonPurchase::factory()->create(['tenant_id' => $tenant->id, 'type' => AddonType::Language, 'item_code' => 'fr', 'status' => AddonPurchaseStatus::Paid]);

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}"))
        ->assertOk()
        ->assertSee('Mostafa Gamal')
        ->assertSee('Saudi Arabia')
        ->assertSee('megz.sa')
        ->assertSee(ComplianceStatus::Verified->label())
        ->assertSee('(paid)')
        ->assertSee(__('platform::vendors.profile.edit_store'))
        ->assertSee('id="impersonate"', false)
        ->assertSee(route('admin.vendors.suspend', $tenant->id), false);
});

it('shows the provisioning banner with retry for failed stores', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant(['status' => TenantStatus::Failed, 'provisioning_error' => 'SQLSTATE connection refused', 'provisioning_attempts' => 2]);

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}"))
        ->assertOk()
        ->assertSee(__('platform::vendors.profile.banner.failed'))
        ->assertSee('SQLSTATE connection refused')
        ->assertSee(route('admin.vendors.retry', $tenant->id), false)
        ->assertDontSee('id="impersonate"', false);
});

it('shows the plan tab with current plan, history and invoice links', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    $plan = Plan::factory()->create(['name' => ['en' => 'Growth', 'ar' => 'نمو']]);
    $subscription = Subscription::factory()->for($plan)->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'billable_type' => 'subscription', 'billable_id' => $subscription->id]);

    $response = $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=plan"))
        ->assertOk()
        ->assertSee('Growth')
        ->assertSee($invoice->number)
        ->assertSee('id="assign-plan-form"', false)
        ->assertSee('name="tenant_id" value="'.$tenant->id.'"', false);

    if (Route::has('admin.finance.invoices.show')) {
        $response->assertSee(route('admin.finance.invoices.show', $invoice->id), false);
    }
});

it('shows the plan tab empty states', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=plan"))
        ->assertOk()
        ->assertSee(__('platform::vendors.plan.none_title'))
        ->assertSee(__('platform::vendors.plan.history_empty_title'));
});

it('shows the domains, commission and compliance tabs', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant(['profit_percent_bps' => 6200, 'affiliate_commission_bps' => 1000]);
    DomainRequest::factory()->create(['tenant_id' => $tenant->id, 'host' => 'megz.sa']);
    $profile = ComplianceProfile::factory()->create(['tenant_id' => $tenant->id]);
    ComplianceDocument::factory()->create(['tenant_id' => $tenant->id, 'compliance_profile_id' => $profile->id, 'document_number' => '1010998221']);

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=domains"))
        ->assertOk()
        ->assertSee($tenant->subdomainHost())
        ->assertSee(__('platform::vendors.domains.primary'))
        ->assertSee('megz.sa');

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=commission"))
        ->assertOk()
        ->assertSee('value="62.00"', false)
        ->assertSee('value="38.00"', false)
        ->assertSee('value="10.00"', false);

    $this->get($this->centralUrl("/admin/vendors/{$tenant->id}?tab=compliance"))
        ->assertOk()
        ->assertDontSee('1010998221')
        ->assertSee(ComplianceDocumentType::CommercialRegistration->label());

    $this->get($this->centralUrl('/admin/vendors/'.vendorsTenant()->id.'?tab=compliance'))
        ->assertOk()
        ->assertSee(__('platform::vendors.compliance.empty_title'));
});

it('updates the store info', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    $uae = Country::factory()->iso('AE')->create();

    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}"), ['name' => '', 'owner_email' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'owner_name', 'owner_email']);

    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}"), [
        'name' => 'Megz Store KSA',
        'owner_name' => 'Mostafa G.',
        'owner_email' => 'New@Megz.com',
        'owner_phone' => '0501234567',
        'owner_phone_country' => 'sa',
        'default_country_id' => $uae->id,
        'selling_country_ids' => [$uae->id],
    ])->assertOk()->assertJsonPath('redirect', route('admin.vendors.show', $tenant->id));

    $tenant->refresh();
    expect($tenant->name)->toBe('Megz Store KSA')
        ->and($tenant->owner_email)->toBe('new@megz.com')
        ->and($tenant->owner_phone)->toBe('+966501234567')
        ->and($tenant->selling_country_ids)->toBe([$uae->id]);
});

it('saves profit and affiliate commission as basis points', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();

    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}/commission"), ['profit_percent' => '101', 'affiliate_commission_percent' => '-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['profit_percent', 'affiliate_commission_percent']);

    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}/commission"), ['profit_percent' => '12.345', 'affiliate_commission_percent' => '10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['profit_percent']);

    $this->putJson($this->centralUrl("/admin/vendors/{$tenant->id}/commission"), ['profit_percent' => '62.55', 'affiliate_commission_percent' => '10'])->assertOk();

    $tenant->refresh();
    expect($tenant->profit_percent_bps)->toBe(6255)
        ->and($tenant->affiliate_commission_bps)->toBe(1000);
});

it('returns 404 for an unknown store', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/vendors/999999'))->assertNotFound();
});
