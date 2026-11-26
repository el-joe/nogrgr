<?php

use App\Modules\Affiliates\DTOs\AdminActor;
use App\Modules\Affiliates\DTOs\PayoutData;
use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Events\AffiliateActivated;
use App\Modules\Affiliates\Events\AffiliateConversionApproved;
use App\Modules\Affiliates\Exceptions\AffiliateRuleException;
use App\Modules\Affiliates\Listeners\ApproveConversionOnPayment;
use App\Modules\Affiliates\Listeners\CreateVendorAffiliate;
use App\Modules\Affiliates\Listeners\RecordReferralOnRegistration;
use App\Modules\Affiliates\Listeners\ReverseConversionOnRefund;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Notifications\AffiliateNotification;
use App\Modules\Affiliates\Services\PayoutService;
use App\Modules\Affiliates\Services\ReferralTracker;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Platform\Events\RegistrationCompleted;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/AffiliateHelpers.php';

/*
| Affiliate lifecycle: ?ref= click + cookie → RegistrationCompleted (pending conversion,
| rate snapshot) → first paid subscription payment (approve + credit) → refund (reverse)
| → payouts allocated oldest first; vendor affiliate per provisioned store; notifications.
*/

beforeEach(function (): void {
    $this->useSettings(IntegrationSection::Affiliates, ['cookie_days' => 30, 'default_commission_bps' => 3000, 'min_payout_cents' => 5000]);
    $this->affiliate = Affiliate::factory()->create(['code' => 'AHMED10X', 'email' => 'ahmed@partners.test']);
});

it('wires the lifecycle listeners', function (): void {
    Event::fake();

    Event::assertListening(RegistrationCompleted::class, RecordReferralOnRegistration::class);
    Event::assertListening(PaymentCaptured::class, ApproveConversionOnPayment::class);
    Event::assertListening(AffiliateConversionReversalRequested::class, ReverseConversionOnRefund::class);
    Event::assertListening(TenantProvisioned::class, CreateVendorAffiliate::class);
});

it('records a click and sets the referral cookie on the central website', function (): void {
    $response = $this->withHeader('User-Agent', 'Mozilla/5.0 Test')->get(route('central.home', ['ref' => 'ahmed10x']));

    $response->assertOk()->assertCookie(ReferralTracker::COOKIE);
    $click = AffiliateClick::query()->sole();
    expect($click->affiliate_id)->toBe($this->affiliate->id)
        ->and($click->landing_url)->toContain('ref=ahmed10x');
});

it('reuses the open click on a repeat visit and ignores bots and unknown codes', function (): void {
    $first = $this->withHeader('User-Agent', 'Mozilla/5.0 Test')->get(route('central.home', ['ref' => 'AHMED10X']));
    $clickId = AffiliateClick::query()->sole()->id;

    $this->withCookie(ReferralTracker::COOKIE, (string) $clickId)->withHeader('User-Agent', 'Mozilla/5.0 Test')->get(route('central.home', ['ref' => 'AHMED10X']))->assertOk();
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('central.home', ['ref' => 'AHMED10X']))->assertOk();
    $this->withHeader('User-Agent', 'Mozilla/5.0 Test')->get(route('central.home', ['ref' => 'NOPE1234']))->assertOk()->assertCookieMissing(ReferralTracker::COOKIE);

    $first->assertCookie(ReferralTracker::COOKIE);
    expect(AffiliateClick::query()->count())->toBe(1);
});

it('does not track suspended affiliates', function (): void {
    $this->affiliate->update(['status' => AccountStatus::Suspended]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 Test')->get(route('central.home', ['ref' => 'AHMED10X']))->assertOk();

    expect(AffiliateClick::query()->count())->toBe(0);
});

it('creates a pending conversion on sign-up with the affiliate rate snapshotted', function (): void {
    $this->affiliate->update(['commission_type' => RateType::Fixed, 'commission_value' => 2500]);
    $click = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $tenant = affiliateTenant();
    $registration = VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $click->id]]);

    app(RecordReferralOnRegistration::class)->handle(new RegistrationCompleted($registration, $tenant));

    $conversion = AffiliateConversion::query()->sole();
    expect($conversion->status)->toBe(ConversionStatus::Pending)
        ->and($conversion->source)->toBe(ConversionSource::Link)
        ->and($conversion->tenant_id)->toBe($tenant->id)
        ->and($conversion->commission_type)->toBe(RateType::Fixed)
        ->and($conversion->commission_value)->toBe(2500)
        ->and($click->refresh()->converted_at)->not->toBeNull()
        ->and($click->tenant_id)->toBe($tenant->id);
});

it('uses the program default rate and ignores expired clicks', function (): void {
    $fresh = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $old = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $old->forceFill(['created_at' => now()->subDays(31)])->save();

    $listener = app(RecordReferralOnRegistration::class);
    $listener->handle(new RegistrationCompleted(VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $old->id]]), affiliateTenant('Old')));
    $listener->handle(new RegistrationCompleted(VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $fresh->id]]), affiliateTenant('Fresh')));

    $conversion = AffiliateConversion::query()->sole();
    expect($conversion->commission_type)->toBe(RateType::Percentage)->and($conversion->commission_value)->toBe(3000);
});

it('attributes a promo code before the click and counts its use', function (): void {
    $other = Affiliate::factory()->create();
    $promo = AffiliatePromoCode::query()->create(['affiliate_id' => $other->id, 'code' => 'SARA15', 'discount_type' => RateType::Percentage, 'discount_value' => 1500, 'commission_type' => RateType::Percentage, 'commission_value' => 2000, 'ends_at' => now()->addMonth()]);
    $click = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $registration = VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $click->id, 'affiliate_promo_code' => 'sara15']]);

    app(RecordReferralOnRegistration::class)->handle(new RegistrationCompleted($registration, affiliateTenant()));

    $conversion = AffiliateConversion::query()->sole();
    expect($conversion->affiliate_id)->toBe($other->id)
        ->and($conversion->source)->toBe(ConversionSource::PromoCode)
        ->and($conversion->commission_value)->toBe(2000)
        ->and($promo->refresh()->uses)->toBe(1);
});

it('rejects a self-referral', function (): void {
    $click = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $tenant = affiliateTenant('Own store', ['owner_email' => 'ahmed@partners.test']);

    app(RecordReferralOnRegistration::class)->handle(new RegistrationCompleted(VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $click->id]]), $tenant));

    expect(AffiliateConversion::query()->sole()->status)->toBe(ConversionStatus::Rejected);
});

it('approves on the first paid subscription payment only (golden 30 % of $792.00)', function (): void {
    Notification::fake();
    $tenant = affiliateTenant();
    $conversion = conversionFor($this->affiliate, $tenant);
    $first = capturedLog($tenant, 79200);
    $renewal = capturedLog($tenant, 79200, ['purpose' => PaymentPurpose::Renewal]);

    app(ApproveConversionOnPayment::class)->handle(new PaymentCaptured($first));
    app(ApproveConversionOnPayment::class)->handle(new PaymentCaptured($first));
    app(ApproveConversionOnPayment::class)->handle(new PaymentCaptured($renewal));

    $conversion->refresh();
    $affiliate = $this->affiliate->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Approved)
        ->and($conversion->payment_log_id)->toBe($first->id)
        ->and($conversion->sale_amount)->toBe(79200)
        ->and($conversion->commission_amount)->toBe(23760)
        ->and($affiliate->balance)->toBe(23760)
        ->and($affiliate->total_earned)->toBe(23760);
    Notification::assertSentTo($affiliate, AffiliateNotification::class, fn (AffiliateNotification $n): bool => $n->eventName === 'conversion_approved');
    Notification::assertSentToTimes($affiliate, AffiliateNotification::class, 1);
});

it('approves a registration paid before the store existed when the sign-up completes', function (): void {
    $tenant = affiliateTenant();
    $log = capturedLog($tenant, 4900, ['tenant_id' => null]);
    $click = AffiliateClick::query()->create(['affiliate_id' => $this->affiliate->id]);
    $registration = VendorRegistration::factory()->create(['meta' => ['affiliate_click_id' => $click->id, 'payment_log_id' => $log->id]]);

    app(RecordReferralOnRegistration::class)->handle(new RegistrationCompleted($registration, $tenant));

    expect(AffiliateConversion::query()->sole())
        ->status->toBe(ConversionStatus::Approved)
        ->commission_amount->toBe(1470);
});

it('ignores unpaid, zero, add-on and storeless payments and keeps pending for suspended affiliates', function (): void {
    $tenant = affiliateTenant();
    $conversion = conversionFor($this->affiliate, $tenant);
    $listener = app(ApproveConversionOnPayment::class);

    $listener->handle(new PaymentCaptured(capturedLog($tenant, 4900, ['status' => PaymentLogStatus::Pending])));
    $listener->handle(new PaymentCaptured(capturedLog($tenant, 0)));
    $listener->handle(new PaymentCaptured(capturedLog($tenant, 4900, ['purpose' => PaymentPurpose::Addon])));
    $listener->handle(new PaymentCaptured(capturedLog($tenant, 4900, ['tenant_id' => null])));
    $this->affiliate->update(['status' => AccountStatus::Suspended]);
    $listener->handle(new PaymentCaptured(capturedLog($tenant, 4900)));

    expect($conversion->refresh()->status)->toBe(ConversionStatus::Pending);
});

it('reverses the conversion when its subscription payment is refunded', function (): void {
    Notification::fake();
    $tenant = affiliateTenant();
    $conversion = conversionFor($this->affiliate, $tenant);
    $log = capturedLog($tenant, 4900);
    app(ApproveConversionOnPayment::class)->handle(new PaymentCaptured($log));

    // A store-order refund carries a refund id (may equal a payment log id) — never a subscription reversal.
    event(new AffiliateConversionReversalRequested($log->id, $tenant->id, 4900, 'USD', 'store_order:'.$tenant->id.':uuid'));
    expect($conversion->refresh()->status)->toBe(ConversionStatus::Approved);

    event(new AffiliateConversionReversalRequested($log->id, $tenant->id, 4900, 'USD', 'Refunded within 14 days.'));

    $conversion->refresh();
    expect($conversion->status)->toBe(ConversionStatus::Reversed)
        ->and($conversion->note)->toBe('Refunded within 14 days.')
        ->and($this->affiliate->refresh()->balance)->toBe(0)
        ->and($this->affiliate->total_earned)->toBe(0);
    Notification::assertSentTo($this->affiliate, AffiliateNotification::class, fn (AffiliateNotification $n): bool => $n->eventName === 'conversion_reversed');
});

it('pays out oldest conversions first, partially when needed', function (): void {
    Notification::fake();
    $old = approvedConversion($this->affiliate, 6000, now()->subDays(3)->toDateTimeString());
    $new = approvedConversion($this->affiliate, 4000, now()->subDay()->toDateTimeString());

    $payout = app(PayoutService::class)->pay($this->affiliate, new PayoutData(7500, PayoutMethod::BankTransfer, 'TRX-1'), new AdminActor(null, 'Sara'));

    expect($payout->amount)->toBe(7500)
        ->and($old->refresh()->status)->toBe(ConversionStatus::Paid)
        ->and($old->paid_amount)->toBe(6000)
        ->and($new->refresh()->status)->toBe(ConversionStatus::Approved)
        ->and($new->paid_amount)->toBe(1500)
        ->and($this->affiliate->refresh()->balance)->toBe(2500)
        ->and($this->affiliate->total_paid)->toBe(7500)
        ->and($this->affiliate->balance)->toBe($this->affiliate->total_earned - $this->affiliate->total_paid);
    Notification::assertSentTo($this->affiliate, AffiliateNotification::class, fn (AffiliateNotification $n): bool => $n->eventName === 'payout_paid');
});

it('enforces the balance and the minimum payout unless it empties the balance', function (): void {
    approvedConversion($this->affiliate, 8000);
    $service = app(PayoutService::class);
    $actor = new AdminActor(null, null);

    expect(fn () => $service->pay($this->affiliate, new PayoutData(9000, PayoutMethod::Paypal), $actor))->toThrow(AffiliateRuleException::class)
        ->and(fn () => $service->pay($this->affiliate, new PayoutData(4000, PayoutMethod::Paypal), $actor))->toThrow(AffiliateRuleException::class)
        ->and(fn () => $service->pay($this->affiliate, new PayoutData(0, PayoutMethod::Paypal), $actor))->toThrow(AffiliateRuleException::class);

    $service->pay($this->affiliate, new PayoutData(5000, PayoutMethod::Paypal), $actor);
    $service->pay($this->affiliate, new PayoutData(3000, PayoutMethod::Paypal), $actor);

    expect($this->affiliate->refresh()->balance)->toBe(0);
});

it('creates one active vendor affiliate per provisioned store', function (): void {
    $tenant = affiliateTenant('Megz Store', ['owner_email' => 'Owner@Megz.test']);
    $other = affiliateTenant('Second Store', ['owner_email' => 'owner@megz.test']);
    $listener = app(CreateVendorAffiliate::class);

    $listener->handle(new TenantProvisioned($tenant));
    $listener->handle(new TenantProvisioned($tenant));
    $listener->handle(new TenantProvisioned($other));

    $affiliate = Affiliate::withoutTenantScope()->where('tenant_id', $tenant->id)->sole();
    expect($affiliate->type)->toBe(AffiliateType::Vendor)
        ->and($affiliate->status)->toBe(AccountStatus::Active)
        ->and($affiliate->email)->toBe('owner@megz.test')
        ->and($affiliate->code)->toHaveLength(8)
        ->and(Affiliate::withoutTenantScope()->where('tenant_id', $other->id)->sole()->email)->toBeNull();
});

it('renders the affiliate e-mails with the built-in layout', function (string $event): void {
    $tenant = affiliateTenant('Megz Store');
    $conversion = conversionFor($this->affiliate, $tenant, ['status' => ConversionStatus::Approved, 'commission_amount' => 23760]);
    $notification = null;
    Notification::fake();

    match ($event) {
        'activated' => event(new AffiliateActivated($this->affiliate)),
        'conversion' => event(new AffiliateConversionApproved($conversion)),
    };

    Notification::assertSentTo($this->affiliate, AffiliateNotification::class, function (AffiliateNotification $sent) use (&$notification): bool {
        $notification = $sent;

        return true;
    });
    $html = (string) $notification->toMail($this->affiliate)->render();

    expect($html)->toContain($event === 'activated' ? 'AHMED10X' : '$237.60');
})->with(['activated', 'conversion']);
