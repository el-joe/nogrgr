<?php

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Notifications\AffiliateNotification;
use App\Modules\Affiliates\Services\ProgramSettingsService;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Integrations\Enums\IntegrationSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/AffiliateHelpers.php';

/*
| Admin › Affiliates (ADM-060 / 060a-c, 061, 062, 063): access, lists (DataTables JSON),
| detail / edit / pay out / settings modals, conversion reverse / reject, payout requests
| pay / reject, promo code CRUD.
*/

beforeEach(function (): void {
    $this->useSettings(IntegrationSection::Affiliates, ['cookie_days' => 30, 'default_commission_bps' => 3000, 'min_payout_cents' => 5000]);
    $this->affiliate = Affiliate::factory()->create(['name' => 'Ahmed Khalil', 'email' => 'ahmed@partners.test']);
});

it('redirects guests and forbids admins without the permission', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('admin.login'));

    $this->actingAs(affiliateAdmin([]), 'admin');
    $this->get(route($route))->assertForbidden();
})->with(['admin.affiliates.index', 'admin.affiliates.conversions.index', 'admin.affiliates.payouts.index', 'admin.affiliates.promo-codes.index', 'admin.affiliates.settings.edit']);

it('renders every page empty', function (string $route, string $empty): void {
    Affiliate::query()->delete();
    $this->actingAs(affiliateAdmin(), 'admin');

    $this->get(route($route))->assertOk()->assertSee(__($empty));
})->with([
    ['admin.affiliates.index', 'affiliates::admin.accounts.empty_title'],
    ['admin.affiliates.conversions.index', 'affiliates::admin.conversions.empty_title'],
    ['admin.affiliates.payouts.index', 'affiliates::admin.payouts.empty_title'],
    ['admin.affiliates.payouts.history', 'affiliates::admin.payouts.history_empty_title'],
    ['admin.affiliates.promo-codes.index', 'affiliates::admin.promo.empty_title'],
]);

it('lists affiliates with type and status filters', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.view']), 'admin');
    Affiliate::factory()->create(['name' => 'Promo Hub', 'status' => AccountStatus::Suspended]);
    approvedConversion($this->affiliate, 33000);

    $this->get(route('admin.affiliates.index'))->assertOk()->assertSee(__('affiliates::admin.accounts.tabs.vendor'))->assertDontSee(__('affiliates::admin.settings.button'));
    $this->getJson(route('admin.affiliates.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->getJson(route('admin.affiliates.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'suspended']))
        ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.affiliate', fn (string $cell): bool => str_contains($cell, 'Promo Hub'));
    $this->getJson(route('admin.affiliates.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => 'ahmed']))
        ->assertOk()->assertJsonPath('data.0.balance', fn (string $cell): bool => str_contains($cell, '$330.00'));
});

it('shows the affiliate detail', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.view']), 'admin');
    approvedConversion($this->affiliate, 1470);

    $this->get(route('admin.affiliates.show', $this->affiliate->id), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Ahmed Khalil')->assertSee($this->affiliate->code)->assertSee('$14.70');
    $this->get(route('admin.affiliates.show', 999999))->assertNotFound();
});

it('edits the commission and activates a pending affiliate (MAIL-038)', function (): void {
    Notification::fake();
    $this->actingAs(affiliateAdmin(['affiliates.view', 'affiliates.manage']), 'admin');
    $pending = Affiliate::factory()->pending()->create();

    $this->get(route('admin.affiliates.edit', $pending->id))->assertOk()->assertSee(__('affiliates::admin.accounts.program_default', ['rate' => '30%']));
    $this->putJson(route('admin.affiliates.update', $pending->id), ['commission_type' => 'percentage', 'commission_value' => '150', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('commission_value');
    $this->putJson(route('admin.affiliates.update', $pending->id), ['commission_type' => 'percentage', 'commission_value' => '12.5', 'status' => 'active'])->assertOk();

    $pending->refresh();
    expect($pending->status)->toBe(AccountStatus::Active)
        ->and($pending->commission_type)->toBe(RateType::Percentage)
        ->and($pending->commission_value)->toBe(1250)
        ->and($pending->approved_at)->not->toBeNull();
    Notification::assertSentTo($pending, AffiliateNotification::class, fn (AffiliateNotification $n): bool => $n->eventName === 'activated');

    $this->putJson(route('admin.affiliates.update', $pending->id), ['commission_type' => '', 'status' => 'suspended'])->assertOk();
    expect($pending->refresh()->commission_type)->toBeNull()->and($pending->status)->toBe(AccountStatus::Suspended);
});

it('needs the manage permission to edit', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.view']), 'admin');

    $this->get(route('admin.affiliates.edit', $this->affiliate->id))->assertForbidden();
    $this->putJson(route('admin.affiliates.update', $this->affiliate->id), ['status' => 'suspended'])->assertForbidden();
});

it('saves the program settings in the database', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.view', 'affiliates.settings.manage']), 'admin');

    $this->get(route('admin.affiliates.settings.edit'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertSee(__('affiliates::admin.settings.title'));
    $this->putJson(route('admin.affiliates.settings.update'), ['cookie_days' => 0, 'default_commission' => 120, 'min_payout' => -1])
        ->assertUnprocessable()->assertJsonValidationErrors(['cookie_days', 'default_commission', 'min_payout']);
    $this->putJson(route('admin.affiliates.settings.update'), ['cookie_days' => 45, 'default_commission' => '25.5', 'min_payout' => '75.00'])->assertOk();

    $settings = app(ProgramSettingsService::class);
    expect($settings->cookieDays())->toBe(45)
        ->and($settings->defaultCommissionBps())->toBe(2550)
        ->and($settings->minPayout()->minor)->toBe(7500);
});

it('pays out an affiliate with a proof file (ADM-060b)', function (): void {
    Storage::fake('central');
    Notification::fake();
    $this->actingAs(affiliateAdmin(['affiliates.view', 'affiliates.payouts.manage']), 'admin');
    approvedConversion($this->affiliate, 41000);

    $this->get(route('admin.affiliates.payout.create', $this->affiliate->id))->assertOk()->assertSee('$410.00')->assertSee('$50.00');
    $this->postJson(route('admin.affiliates.payout.store', $this->affiliate->id), ['amount' => '500', 'method' => 'paypal'])->assertUnprocessable();
    $this->postJson(route('admin.affiliates.payout.store', $this->affiliate->id), ['amount' => '20', 'method' => 'paypal'])->assertUnprocessable();
    $this->post(route('admin.affiliates.payout.store', $this->affiliate->id), [
        'amount' => '410.00', 'method' => 'bank_transfer', 'reference' => 'PP-7781-22',
        'attachment' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertOk();

    $payout = AffiliatePayout::query()->sole();
    expect($payout->amount)->toBe(41000)->and($this->affiliate->refresh()->balance)->toBe(0);
    Storage::disk('central')->assertExists($payout->attachment);

    $this->actingAs(affiliateAdmin(['affiliates.payouts.view']), 'admin');
    $this->get(route('admin.affiliates.payouts.attachment', $payout->id))->assertOk();
    $this->getJson(route('admin.affiliates.payouts.data', ['history' => 1, 'draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->assertJsonPath('recordsTotal', 1);
});

it('lists, reverses and rejects conversions (ADM-061)', function (): void {
    Notification::fake();
    $this->actingAs(affiliateAdmin(['affiliates.conversions.view', 'affiliates.conversions.manage']), 'admin');
    $approved = approvedConversion($this->affiliate, 2900);
    $pending = conversionFor($this->affiliate, affiliateTenant('Kids Land'));

    $this->getJson(route('admin.affiliates.conversions.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->getJson(route('admin.affiliates.conversions.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'pending']))->assertOk()->assertJsonPath('recordsFiltered', 1);
    $this->get(route('admin.affiliates.conversions.show', $approved->id))->assertOk()->assertSee($approved->reference());
    $this->get(route('admin.affiliates.conversions.reverse.form', $approved->id))->assertOk();

    $this->postJson(route('admin.affiliates.conversions.reverse', $approved->id), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('admin.affiliates.conversions.reverse', $pending->id), ['reason' => 'x'])->assertUnprocessable();
    $this->postJson(route('admin.affiliates.conversions.reverse', $approved->id), ['reason' => 'Subscription refunded within 14 days.'])->assertOk();
    $this->postJson(route('admin.affiliates.conversions.reject', $pending->id))->assertOk();
    $this->postJson(route('admin.affiliates.conversions.reject', $pending->id))->assertUnprocessable();

    expect($approved->refresh()->status)->toBe(ConversionStatus::Reversed)
        ->and($pending->refresh()->status)->toBe(ConversionStatus::Rejected)
        ->and($this->affiliate->refresh()->balance)->toBe(0);
});

it('forbids conversion actions without the manage permission', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.conversions.view']), 'admin');
    $approved = approvedConversion($this->affiliate, 2900);

    $this->postJson(route('admin.affiliates.conversions.reverse', $approved->id), ['reason' => 'x'])->assertForbidden();
});

it('marks a payout request paid or rejects it (ADM-062, MAIL-040)', function (): void {
    Notification::fake();
    $this->actingAs(affiliateAdmin(['affiliates.payouts.view', 'affiliates.payouts.manage']), 'admin');
    approvedConversion($this->affiliate, 63000);
    $paid = AffiliatePayoutRequest::query()->create(['affiliate_id' => $this->affiliate->id, 'amount' => 41000, 'note' => 'PayPal please']);
    $rejected = AffiliatePayoutRequest::query()->create(['affiliate_id' => $this->affiliate->id, 'amount' => 22000]);

    $this->get(route('admin.affiliates.payouts.index'))->assertOk()->assertSee(__('affiliates::admin.activity.payouts'));
    $this->getJson(route('admin.affiliates.payouts.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'pending']))->assertOk()->assertJsonPath('recordsFiltered', 2);
    $this->get(route('admin.affiliates.payouts.show', $paid->id))->assertOk()->assertSee(__('affiliates::admin.payout.mark_paid'))->assertSee('PayPal please');
    $this->get(route('admin.affiliates.payouts.reject.form', $rejected->id))->assertOk();

    $this->postJson(route('admin.affiliates.payouts.pay', $paid->id), ['amount' => '410.00', 'method' => 'paypal', 'reference' => 'PP-1'])->assertOk();
    $this->postJson(route('admin.affiliates.payouts.pay', $paid->id), ['amount' => '50.00', 'method' => 'paypal'])->assertUnprocessable();
    $this->postJson(route('admin.affiliates.payouts.reject', $rejected->id), ['note' => 'Add your IBAN first.'])->assertOk();

    expect($paid->refresh()->status)->toBe(PayoutRequestStatus::Paid)
        ->and($paid->payout_id)->not->toBeNull()
        ->and($rejected->refresh()->status)->toBe(PayoutRequestStatus::Rejected)
        ->and($rejected->admin_note)->toBe('Add your IBAN first.')
        ->and($this->affiliate->refresh()->balance)->toBe(22000);
    Notification::assertSentTo($this->affiliate, AffiliateNotification::class, fn (AffiliateNotification $n): bool => $n->eventName === 'payout_rejected');
    $this->get(route('admin.affiliates.payouts.show', $paid->id))->assertOk()->assertSee('PP-1');
});

it('manages promo codes (ADM-063)', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.promo-codes.view', 'affiliates.promo-codes.manage']), 'admin');
    $payload = ['affiliate_id' => $this->affiliate->id, 'code' => 'ahmed10', 'discount_type' => 'percentage', 'discount_value' => '10', 'ends_at' => now()->addMonth()->toDateString(), 'is_active' => '1'];

    $this->getJson(route('admin.affiliates.promo-codes.affiliates', ['q' => 'ahmed']))->assertOk()->assertJsonPath('results.0.id', $this->affiliate->id);
    $this->get(route('admin.affiliates.promo-codes.create'))->assertOk();
    $this->postJson(route('admin.affiliates.promo-codes.store'), [...$payload, 'discount_value' => '120', 'ends_at' => now()->subDay()->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors(['discount_value', 'ends_at']);
    $this->postJson(route('admin.affiliates.promo-codes.store'), $payload)->assertCreated();
    $this->postJson(route('admin.affiliates.promo-codes.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('code');

    $code = AffiliatePromoCode::query()->sole();
    expect($code->code)->toBe('AHMED10')->and($code->discount_value)->toBe(1000)->and($code->isUsable())->toBeTrue();

    $this->getJson(route('admin.affiliates.promo-codes.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()
        ->assertJsonPath('data.0.discount', fn (string $cell): bool => str_contains($cell, '10%'));
    $this->get(route('admin.affiliates.promo-codes.edit', $code->id))->assertOk()->assertSee('AHMED10');
    $this->putJson(route('admin.affiliates.promo-codes.update', $code->id), [...$payload, 'discount_type' => 'fixed', 'discount_value' => '15', 'is_active' => '0'])->assertOk();
    expect($code->refresh()->discount_type)->toBe(RateType::Fixed)->and($code->is_active)->toBeFalse();

    conversionFor($this->affiliate, affiliateTenant(), ['promo_code_id' => $code->id, 'source' => 'promo_code']);
    $this->deleteJson(route('admin.affiliates.promo-codes.destroy', $code->id))->assertUnprocessable();
    AffiliatePromoCode::query()->create(['affiliate_id' => $this->affiliate->id, 'code' => 'FREE5', 'discount_type' => 'fixed', 'discount_value' => 500, 'ends_at' => now()->addDay()]);
    $this->deleteJson(route('admin.affiliates.promo-codes.destroy', AffiliatePromoCode::query()->where('code', 'FREE5')->value('id')))->assertOk();
});

it('forbids promo code changes for view-only admins', function (): void {
    $this->actingAs(affiliateAdmin(['affiliates.promo-codes.view']), 'admin');

    $this->get(route('admin.affiliates.promo-codes.create'))->assertForbidden();
    $this->getJson(route('admin.affiliates.promo-codes.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
});

it('feeds the Reports › Affiliates tab from the program tables (ADM-070h)', function (): void {
    $this->actingAs(affiliateAdmin(['reports.view']), 'admin');
    $tenant = affiliateTenant('Megz Store');
    AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id, 'tenant_id' => $tenant->id]);
    AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    approvedConversion($this->affiliate, 1470);
    conversionFor($this->affiliate, $tenant, ['status' => ConversionStatus::Rejected, 'commission_amount' => 999]);
    AffiliatePayout::query()->create(['affiliate_id' => $this->affiliate->id, 'amount' => 1000, 'method' => 'paypal', 'paid_at' => now()]);

    $this->getJson(route('admin.reports.data', ['tab' => 'affiliates', 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('data.0.c_clicks', fn (mixed $value): bool => strip_tags((string) $value) === '2')
        ->assertJsonPath('data.0.c_commission', fn (mixed $value): bool => strip_tags((string) $value) === '$14.70');
});
