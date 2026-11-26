<?php

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Integrations\Enums\IntegrationSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/AffiliateHelpers.php';

/*
| Affiliate panel (AFF-008…015): access, every page (empty + filled), own-data scoping of
| every list, payout requests (requestable = balance − open requests, ≥ minimum), profile,
| payout details (encrypted IBAN), password, notifications inbox, panel error pages.
*/

function panelAffiliate(array $attributes = [], bool $withRole = true): Affiliate
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(CentralPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $affiliate = Affiliate::factory()->create(array_merge(['locale' => 'en'], $attributes));

    if ($withRole) {
        $affiliate->assignRole('affiliate');
    }

    return $affiliate;
}

function panelRows(string $url): array
{
    $separator = str_contains($url, '?') ? '&' : '?';

    return test()->getJson($url.$separator.'draw=1&start=0&length=50')->assertOk()->json('data');
}

function affiliateInboxRow(Affiliate $affiliate): string
{
    $id = (string) Str::uuid();
    $affiliate->notifications()->getRelated()->newQuery()->insert([
        'id' => $id,
        'type' => 'payments.affiliate_payout_paid',
        'notifiable_type' => $affiliate->getMorphClass(),
        'notifiable_id' => $affiliate->id,
        'data' => json_encode(['category' => 'payments', 'event' => 'affiliate_payout_paid', 'title_key' => 'affiliates::panel.payouts.title', 'body_key' => 'affiliates::panel.payouts.subtitle']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

beforeEach(function (): void {
    $this->useSettings(IntegrationSection::Affiliates, ['cookie_days' => 30, 'default_commission_bps' => 3000, 'min_payout_cents' => 5000]);
});

dataset('affiliate pages', [
    'dashboard' => ['affiliate.dashboard', 'affiliates::panel.dashboard.chart_empty_title'],
    'links' => ['affiliate.links', 'affiliates::panel.links.link_title'],
    'conversions' => ['affiliate.conversions', 'affiliates::panel.conversions.empty_title'],
    'payouts' => ['affiliate.payouts.index', 'affiliates::panel.payouts.requests_empty_title'],
    'profile' => ['affiliate.profile.edit', 'affiliates::panel.profile.personal'],
    'notifications' => ['affiliate.notifications.index', 'notifications::panel.inbox.empty_title'],
]);

it('sends guests to the affiliate login', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('affiliate.login'));
})->with('affiliate pages');

it('forbids an affiliate without the panel permissions and shows the panel 403 page', function (string $route): void {
    $this->actingAs(panelAffiliate(withRole: false), 'affiliate')
        ->get(route($route))
        ->assertForbidden()
        ->assertSee(__('affiliates::panel.errors.403_title'));
})->with('affiliate pages');

it('renders every page with its empty state and the partner navigation', function (string $route, string $empty): void {
    $this->actingAs(panelAffiliate(), 'affiliate')
        ->get(route($route))
        ->assertOk()
        ->assertSee(__($empty))
        ->assertSee(__('navigation.affiliate.links'))
        ->assertSee(__('navigation.affiliate.payouts'));
})->with('affiliate pages');

it('renders the pages in arabic', function (): void {
    $this->actingAs(panelAffiliate(), 'affiliate')
        ->get(route('affiliate.payouts.index', ['lang' => 'ar']))
        ->assertOk()
        ->assertSee('طلبات السحب')
        ->assertSee('dir="rtl"', false);
});

it('shows the dashboard KPIs in USD with the referral link and recent conversions', function (): void {
    $affiliate = panelAffiliate(['code' => 'SARA24AA']);
    AffiliateClick::query()->create(['affiliate_id' => $affiliate->id, 'ip' => '1.1.1.1']);
    AffiliateClick::query()->create(['affiliate_id' => $affiliate->id, 'ip' => '1.1.1.2']);
    approvedConversion($affiliate, 8600);
    conversionFor($affiliate, affiliateTenant('Pending Shop'), ['commission_amount' => 1240]);

    $this->actingAs($affiliate, 'affiliate')
        ->get(route('affiliate.dashboard'))
        ->assertOk()
        ->assertSee('?ref=SARA24AA')
        ->assertSee('$86.00')
        ->assertSee('$12.40')
        ->assertSee('Pending Shop')
        ->assertSee('payout-request-modal', false)
        ->assertSee(__('affiliates::panel.dashboard.clicks'));
});

it('lists only the signed-in affiliate conversions, filtered by status', function (): void {
    $affiliate = panelAffiliate();
    $other = panelAffiliate();
    approvedConversion($affiliate, 3000);
    conversionFor($affiliate, affiliateTenant('Mine Pending'));
    conversionFor($other, affiliateTenant('Not Mine'));

    $this->actingAs($affiliate, 'affiliate');
    $rows = panelRows(route('affiliate.conversions.data'));
    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('store')->implode(''))->not->toContain('Not Mine');

    expect(panelRows(route('affiliate.conversions.data', ['status' => ConversionStatus::Pending->value])))->toHaveCount(1);
});

it('lists only the own payout requests, payouts and promo codes', function (): void {
    $affiliate = panelAffiliate();
    $other = panelAffiliate();

    foreach ([$affiliate, $other] as $owner) {
        AffiliatePayoutRequest::query()->create(['affiliate_id' => $owner->id, 'amount' => 6000, 'status' => PayoutRequestStatus::Pending]);
        AffiliatePayout::query()->create(['affiliate_id' => $owner->id, 'amount' => 6000, 'method' => PayoutMethod::Paypal, 'reference' => 'TX-'.$owner->id, 'paid_at' => now()]);
        AffiliatePromoCode::query()->create(['affiliate_id' => $owner->id, 'code' => 'CODE'.$owner->id, 'discount_type' => 'percentage', 'discount_value' => 1000]);
    }

    $this->actingAs($affiliate, 'affiliate');

    expect(panelRows(route('affiliate.payouts.requests.data')))->toHaveCount(1)
        ->and(panelRows(route('affiliate.payouts.history.data')))->toHaveCount(1)
        ->and($codes = panelRows(route('affiliate.links.promo-codes')))->toHaveCount(1)
        ->and($codes[0]['code'])->toContain('CODE'.$affiliate->id);
});

it('validates a payout request and enforces payout details, the minimum and the requestable amount', function (): void {
    $affiliate = panelAffiliate();
    approvedConversion($affiliate, 12000);
    $this->actingAs($affiliate, 'affiliate');
    $url = route('affiliate.payouts.requests.store');

    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson($url, ['amount' => '60.00'])->assertUnprocessable()->assertJsonValidationErrors('amount')
        ->assertJsonPath('errors.amount.0', __('affiliates::panel.payouts.errors.no_details'));

    $affiliate->forceFill(['payout_method' => PayoutMethod::Paypal, 'paypal_email' => 'sara@pay.test'])->save();

    $this->postJson($url, ['amount' => '20.00'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson($url, ['amount' => '120.01'])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->postJson($url, ['amount' => '70.00', 'note' => 'March'])->assertCreated()->assertJsonPath('redirect', route('affiliate.payouts.index'));
    expect(AffiliatePayoutRequest::query()->where('affiliate_id', $affiliate->id)->first())
        ->amount->toBe(7000)
        ->status->toBe(PayoutRequestStatus::Pending)
        ->note->toBe('March');

    // Requestable is now 120 − 70 = 50: 50.01 is too much, 50.00 (= the minimum) passes.
    $this->postJson($url, ['amount' => '50.01'])->assertUnprocessable();
    $this->postJson($url, ['amount' => '50.00'])->assertCreated();
    $this->postJson($url, ['amount' => '50.00'])->assertUnprocessable();
});

it('refuses payout requests of a suspended affiliate', function (): void {
    $affiliate = panelAffiliate(['payout_method' => PayoutMethod::Paypal, 'paypal_email' => 'x@pay.test']);
    approvedConversion($affiliate, 9000);
    $affiliate->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($affiliate, 'affiliate')
        ->postJson(route('affiliate.payouts.requests.store'), ['amount' => '60.00'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.amount.0', __('affiliates::panel.payouts.errors.inactive'));
});

it('updates the profile with validation', function (): void {
    $affiliate = panelAffiliate();
    $this->actingAs($affiliate, 'affiliate');

    $this->putJson(route('affiliate.profile.update'), ['name' => '', 'locale' => 'xx'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'locale']);

    $this->putJson(route('affiliate.profile.update'), ['name' => 'Sara Ahmed', 'phone' => '0501234567', 'phone_country' => 'sa', 'locale' => 'en'])
        ->assertOk()->assertJsonPath('success', true);

    expect($affiliate->fresh())->name->toBe('Sara Ahmed')->phone->toBe('+966501234567');
});

it('saves payout details with the IBAN encrypted and only the chosen method fields', function (): void {
    $affiliate = panelAffiliate(['paypal_email' => 'old@pay.test']);
    $this->actingAs($affiliate, 'affiliate');
    $url = route('affiliate.profile.payout');

    $this->putJson($url, ['payout_method' => 'bank_transfer'])->assertUnprocessable()
        ->assertJsonValidationErrors(['bank_name', 'bank_account_name', 'bank_iban']);
    $this->putJson($url, ['payout_method' => 'bank_transfer', 'bank_name' => 'SNB', 'bank_account_name' => 'Sara', 'bank_iban' => 'not an iban'])
        ->assertUnprocessable()->assertJsonValidationErrors('bank_iban');
    $this->putJson($url, ['payout_method' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('payout_method');

    $this->putJson($url, ['payout_method' => 'bank_transfer', 'bank_name' => 'SNB', 'bank_account_name' => 'Sara Ahmed', 'bank_iban' => 'sa03 8000 0000 6080 1016 7519'])->assertOk();

    $fresh = $affiliate->fresh();
    expect($fresh)->payout_method->toBe(PayoutMethod::BankTransfer)
        ->bank_iban->toBe('SA0380000000608010167519')
        ->paypal_email->toBeNull();
    expect(DB::connection($affiliate->getConnectionName())->table('affiliates')->where('id', $affiliate->id)->value('bank_iban'))
        ->not->toContain('SA0380000000608010167519');
});

it('changes the password after checking the current one', function (): void {
    $affiliate = panelAffiliate();
    $this->actingAs($affiliate, 'affiliate');

    $this->putJson(route('affiliate.profile.password'), ['current_password' => 'wrong', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson(route('affiliate.profile.password'), ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson(route('affiliate.profile.password'), ['current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
        ->assertOk();

    expect(Hash::check('NewPass123', (string) $affiliate->fresh()->password))->toBeTrue();
});

it('manages only the own notifications', function (): void {
    $affiliate = panelAffiliate();
    $other = panelAffiliate();
    $mine = affiliateInboxRow($affiliate);
    $theirs = affiliateInboxRow($other);
    $this->actingAs($affiliate, 'affiliate');

    expect(panelRows(route('affiliate.notifications.data')))->toHaveCount(1);
    $this->getJson(route('affiliate.notifications.latest'))->assertOk()->assertJsonPath('data.unread', 1);

    $this->patchJson(route('affiliate.notifications.mark', $theirs), ['read' => 1])->assertNotFound();
    $this->patchJson(route('affiliate.notifications.mark', $mine), ['read' => 1])->assertOk()->assertJsonPath('data.unread', 0);
    $this->postJson(route('affiliate.notifications.read-all'))->assertOk();
    $this->deleteJson(route('affiliate.notifications.destroy', $theirs))->assertNotFound();
    $this->deleteJson(route('affiliate.notifications.destroy', $mine))->assertOk();

    expect($other->notifications()->count())->toBe(1);
});

it('renders the affiliate panel 404 page', function (): void {
    $this->actingAs(panelAffiliate(), 'affiliate')
        ->get($this->centralUrl('affiliate/does-not-exist'))
        ->assertNotFound()
        ->assertSee(__('affiliates::panel.errors.404_title'))
        ->assertSee(route('affiliate.dashboard'), false);
});
