<?php

use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Platform\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/AffiliateHelpers.php';

/*
| Vendor › Partner program (VEN-055 / 055a / 009b): access, the store's vendor affiliate,
| its conversions / payouts lists, "Withdraw" (payout request), SSO into the affiliate
| panel and the dashboard banner.
*/

function partnerStaff(Tenant $tenant, array $permissions): VendorStaff
{
    return $tenant->run(function () use ($permissions): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', ...$permissions]);

        return $staff;
    });
}

beforeEach(function (): void {
    $this->useSettings(IntegrationSection::Affiliates, ['cookie_days' => 30, 'default_commission_bps' => 3000, 'min_payout_cents' => 5000]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(CentralPermissionSeeder::class);
    $this->tenant = $this->createTenant('partnerco');
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->affiliate = app(AffiliateAccountService::class)->createForTenant($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/partner-program'.$path);
    $this->as = fn (?VendorStaff $staff = null) => $this->actingAsVendor($staff ?? $this->owner, $this->tenant);
});

it('sends guests to the store login and forbids staff without the partner permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $nobody = partnerStaff($this->tenant, []);
    ($this->as)($nobody)->get(($this->url)())->assertForbidden();
    ($this->as)($nobody)->getJson(($this->url)('/conversions/data'))->assertForbidden();

    $viewer = partnerStaff($this->tenant, ['partner.view']);
    ($this->as)($viewer)->get(($this->url)())->assertOk()->assertDontSee(__('affiliates::panel.partner.open_dashboard'));
    ($this->as)($viewer)->postJson(($this->url)('/withdraw'), ['amount' => '60'])->assertForbidden();
    ($this->as)($viewer)->post(($this->url)('/sso'))->assertForbidden();
});

it('shows the store affiliate with its link, KPIs and empty lists', function (): void {
    ($this->as)()->get(($this->url)())
        ->assertOk()
        ->assertSee(__('affiliates::panel.partner.title'))
        ->assertSee('?ref='.$this->affiliate->code)
        ->assertSee(__('affiliates::panel.partner.open_dashboard'))
        ->assertSee('$0.00')
        ->assertSee(__('affiliates::panel.payouts.below_minimum_notice', ['amount' => '$50.00']));

    expect(Affiliate::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('lists only the store affiliate conversions and payouts', function (): void {
    approvedConversion($this->affiliate, 8700);
    $other = Affiliate::factory()->create();
    approvedConversion($other, 1000);

    $rows = ($this->as)()->getJson(($this->url)('/conversions/data?draw=1&start=0&length=10'))->assertOk()->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['commission'])->toContain('$87.00');

    ($this->as)()->getJson(($this->url)('/payouts/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 0);
});

it('withdraws the requestable balance without payout details and enforces the limits', function (): void {
    approvedConversion($this->affiliate, 18000);

    ($this->as)()->get(($this->url)())->assertOk()->assertSee('payout-request-modal', false);
    ($this->as)()->postJson(($this->url)('/withdraw'), [])->assertUnprocessable()->assertJsonValidationErrors('amount');
    ($this->as)()->postJson(($this->url)('/withdraw'), ['amount' => '10.00'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    ($this->as)()->postJson(($this->url)('/withdraw'), ['amount' => '180.01'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    ($this->as)()->postJson(($this->url)('/withdraw'), ['amount' => '180.00'])->assertCreated();

    expect(AffiliatePayoutRequest::query()->where('affiliate_id', $this->affiliate->id)->sole())
        ->amount->toBe(18000)->status->toBe(PayoutRequestStatus::Pending);
    ($this->as)()->postJson(($this->url)('/withdraw'), ['amount' => '50.00'])->assertUnprocessable();
});

it('opens the affiliate dashboard through a one-time SSO link', function (): void {
    $response = ($this->as)()->post(($this->url)('/sso'));
    $target = (string) $response->headers->get('Location');
    expect($target)->toStartWith($this->centralUrl('affiliate/sso/'));

    $this->get($target)->assertRedirect(route('affiliate.dashboard'));
    $this->assertAuthenticatedAs($this->affiliate->fresh(), 'affiliate');
});

it('shows the partner banner on the vendor dashboard for staff with access only', function (): void {
    ($this->as)()->get($this->tenantUrl($this->tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertSee('data-partner-widget', false)
        ->assertSee('?ref='.$this->affiliate->code);

    ($this->as)(partnerStaff($this->tenant, []))->get($this->tenantUrl($this->tenant, 'admin/dashboard'))
        ->assertOk()
        ->assertDontSee('data-partner-widget', false);
});
