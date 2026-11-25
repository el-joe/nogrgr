<?php

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Plan;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Notifications\RegistrationLinkNotification;
use App\Modules\Platform\Services\RegistrationFlowService;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/SignUpHelpers.php';

/*
| WEB-016 sign up, WEB-016b plan step (trial path) and WEB-017 e-mail verification
| (resend cooldown, limit, change e-mail).
*/

beforeEach(function (): void {
    Notification::fake();
});

describe('sign up', function (): void {
    it('renders the form with the phone field and no social button when no provider is enabled', function (): void {
        config(['services.google.enabled' => false, 'services.apple.enabled' => false]);

        $this->get(route('central.register'))
            ->assertOk()
            ->assertSee('data-phone', false)
            ->assertSee('name="compliance"', false)
            ->assertDontSee('name="provider"', false);
    });

    it('shows only the enabled social providers', function (): void {
        config(['services.google' => ['enabled' => true, 'client_id' => 'gid', 'client_secret' => 'gsecret'], 'services.apple.enabled' => false]);

        $this->get(route('central.register'))
            ->assertOk()
            ->assertSee('value="google"', false)
            ->assertDontSee('value="apple"', false);
    });

    it('validates e-mail, phone and the compliance accept', function (): void {
        $this->post(route('central.register.store'), ['email' => 'not-an-email', 'phone' => '12', 'phone_country' => 'sa'])
            ->assertSessionHasErrors(['email', 'phone', 'compliance']);

        expect(VendorRegistration::query()->count())->toBe(0);
    });

    it('asks for the e-mail without exposing the internal provider rule', function (): void {
        $this->post(route('central.register.store'), ['phone' => '501234567', 'phone_country' => 'sa', 'compliance' => '1'])
            ->assertSessionHasErrors(['email' => __('validation.required', ['attribute' => __('platform::signup.form.email')])]);

        expect(session('errors')->first('email'))->not->toContain('provider');
    });

    it('keeps the sign-up in the session until a plan is chosen', function (): void {
        $this->post(route('central.register.store'), signUpPayload())->assertRedirect(route('central.register.plan'));

        expect(VendorRegistration::query()->count())->toBe(0);

        $plan = Plan::factory()->withTrial()->create();
        Plan::factory()->inactive()->create(['name' => ['en' => 'Hidden plan']]);

        $this->get(route('central.register.plan'))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertDontSee('Hidden plan');
    });

    it('sends the plan step back to sign-up without a draft', function (): void {
        $this->get(route('central.register.plan'))->assertRedirect(route('central.register'));
        $this->post(route('central.register.plan.store'), ['plan_id' => 1, 'term' => 'monthly', 'choice' => 'trial'])->assertRedirect(route('central.register'));
    });

    it('is throttled', function (): void {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('central.register.store'), ['email' => 'x']);
        }

        $this->post(route('central.register.store'), ['email' => 'x'])->assertStatus(429);
    });
});

describe('plan step', function (): void {
    it('starts a trial: creates the registration and e-mails the completion link', function (): void {
        $plan = paidPlan(trialDays: 14);

        signUpAndChoose($plan, 'trial', 'yearly')->assertRedirect(route('central.register.verify'));

        $registration = VendorRegistration::query()->sole();

        expect($registration->status)->toBe(RegistrationStatus::EmailUnverified)
            ->and($registration->email)->toBe('founder@example.com')
            ->and($registration->phone)->toBe('+966501234567')
            ->and($registration->plan_id)->toBe($plan->id)
            ->and($registration->term)->toBe(BillingTerm::Yearly)
            ->and($registration->meta['compliance_accepted_at'] ?? null)->not->toBeNull()
            ->and($registration->link_sent_count)->toBe(1);

        Notification::assertSentOnDemand(RegistrationLinkNotification::class);

        $this->get(route('central.register.verify'))->assertOk()->assertSee('founder@example.com');
    });

    it('refuses a trial on a plan without one', function (): void {
        $plan = paidPlan(trialDays: 0);

        signUpAndChoose($plan, 'trial')->assertSessionHas('error', __('platform::signup.errors.trial_unavailable'));

        expect(VendorRegistration::query()->count())->toBe(0);
    });

    it('sends a paid choice to the payment step without e-mailing a link', function (): void {
        $plan = paidPlan();

        signUpAndChoose($plan, 'paid')->assertRedirect(route('central.register.payment'));

        expect(VendorRegistration::query()->sole()->status)->toBe(RegistrationStatus::PaymentPending);
        Notification::assertNothingSent();
    });

    it('treats a free plan as no payment', function (): void {
        $plan = Plan::factory()->create(['monthly_price_minor' => 0, 'yearly_price_minor' => 0]);

        signUpAndChoose($plan, 'paid')->assertRedirect(route('central.register.verify'));

        expect(VendorRegistration::query()->sole()->status)->toBe(RegistrationStatus::EmailUnverified);
    });

    it('rejects an inactive plan', function (): void {
        $plan = Plan::factory()->inactive()->create();

        signUpAndChoose($plan, 'paid')->assertSessionHas('error', __('platform::signup.errors.plan_unavailable'));
    });
});

describe('verify e-mail', function (): void {
    it('enforces the resend cooldown, then resends', function (): void {
        signUpAndChoose(paidPlan(14), 'trial');

        $this->post(route('central.register.verify.resend'))
            ->assertRedirect(route('central.register.verify'))
            ->assertSessionHas('error');

        expect(VendorRegistration::query()->sole()->link_sent_count)->toBe(1);

        $this->travel(RegistrationFlowService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

        $this->post(route('central.register.verify.resend'))->assertSessionHas('resent', true);

        expect(VendorRegistration::query()->sole()->link_sent_count)->toBe(2);
        Notification::assertSentOnDemandTimes(RegistrationLinkNotification::class, 2);

        $this->get(route('central.register.verify'))->assertOk()->assertSee(__('platform::signup.verify.resent'));
    });

    it('stops at the resend limit', function (): void {
        signUpAndChoose(paidPlan(14), 'trial');
        VendorRegistration::query()->update(['link_sent_count' => RegistrationFlowService::MAX_LINK_SENDS, 'link_sent_at' => now()->subHour()]);

        $this->post(route('central.register.verify.resend'))->assertSessionHas('error', __('platform::signup.verify.limit_reached'));
        $this->get(route('central.register.verify'))->assertSee(__('platform::signup.verify.limit_reached'));
    });

    it('changes the e-mail and sends a new link', function (): void {
        signUpAndChoose(paidPlan(14), 'trial');

        $this->post(route('central.register.verify.email'), ['email' => 'new@Example.com'])->assertSessionHas('resent', true);

        $registration = VendorRegistration::query()->sole();
        expect($registration->email)->toBe('new@example.com')->and($registration->link_sent_count)->toBe(2);
    });

    it('redirects without a registration in the session', function (): void {
        $this->get(route('central.register.verify'))->assertRedirect(route('central.register'));
        $this->post(route('central.register.verify.resend'))->assertRedirect(route('central.register'));
    });
});

describe('signed-in owner', function (): void {
    it('skips the e-mail check and goes straight to the completion link', function (): void {
        $owner = Owner::factory()->create(['email' => 'owner@example.com']);
        $this->actingAs($owner, 'owner');

        $this->get(route('central.register'))->assertOk()->assertSee('owner@example.com');

        $response = signUpAndChoose(paidPlan(14), 'trial', 'monthly', ['email' => 'ignored@example.com']);
        $response->assertRedirect(route('central.register.continue'));

        $registration = VendorRegistration::query()->sole();
        expect($registration->email)->toBe('owner@example.com')
            ->and($registration->owner_id)->toBe($owner->id)
            ->and($registration->status)->toBe(RegistrationStatus::Incomplete)
            ->and($registration->email_verified_at)->not->toBeNull();

        $continue = $this->get(route('central.register.continue'));
        expect($continue->headers->get('Location'))->toContain('/register/complete?')->toContain('signature=');
        Notification::assertNothingSent();

        $this->get($continue->headers->get('Location'))->assertOk();
    });
});
