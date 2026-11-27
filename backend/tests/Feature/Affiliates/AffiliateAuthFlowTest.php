<?php

use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Models\AffiliateSsoToken;
use App\Modules\Affiliates\Notifications\AffiliateVerifyEmailNotification;
use App\Modules\Affiliates\Services\AffiliateSsoService;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Integrations\Enums\IntegrationSection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/AffiliateHelpers.php';

/*
| Affiliate sign-up (AFF-002 / 003), pending / suspended login errors (AFF-001), e-mail
| confirmation (AFF-006) and the vendor-panel SSO landing (AFF-007).
*/

beforeEach(function (): void {
    $this->useSettings(IntegrationSection::Affiliates, ['cookie_days' => 30, 'default_commission_bps' => 3000, 'min_payout_cents' => 5000]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(CentralPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('shows the sign-up page and links it from the login page', function (): void {
    $this->get(route('affiliate.register'))->assertOk()->assertSee(__('affiliates::panel.register.title'))->assertSee('30%');
    $this->get(route('affiliate.login'))->assertOk()->assertSee(route('affiliate.register'), false);
});

it('validates the sign-up form', function (): void {
    Affiliate::factory()->create(['email' => 'taken@partners.test']);

    $this->from(route('affiliate.register'))->post(route('affiliate.register.store'), [
        'name' => '',
        'email' => 'taken@partners.test',
        'password' => 'short',
        'password_confirmation' => 'other',
    ])->assertRedirect(route('affiliate.register'))->assertSessionHasErrors(['name', 'email', 'password', 'terms']);

    $this->post(route('affiliate.register.store'), [
        'name' => 'Sara', 'email' => 'sara@partners.test', 'phone' => '123', 'phone_country' => 'SA',
        'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'terms' => '1',
    ])->assertSessionHasErrors('phone');
});

it('creates a pending partner, mails the confirmation link and shows the pending notice', function (): void {
    Notification::fake();

    $this->post(route('affiliate.register.store'), [
        'name' => 'Sara Ahmed',
        'email' => 'Sara@Partners.test',
        'phone' => '0501234567',
        'phone_country' => 'SA',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
        'terms' => '1',
    ])->assertRedirect(route('affiliate.register'));

    $affiliate = Affiliate::query()->where('email', 'sara@partners.test')->firstOrFail();
    expect($affiliate)->status->toBe(AccountStatus::Pending)
        ->type->toBe(AffiliateType::Independent)
        ->phone->toBe('+966501234567')
        ->code->not->toBeNull()
        ->and($affiliate->hasRole('affiliate'))->toBeTrue()
        ->and($affiliate->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($affiliate, AffiliateVerifyEmailNotification::class);
    $this->assertGuest('affiliate');

    $this->get(route('affiliate.register'))->assertOk()->assertSee(__('affiliates::panel.pending.title'))->assertSee('sara@partners.test');
});

it('refuses pending and suspended partners at login with their own message', function (): void {
    Affiliate::factory()->pending()->create(['email' => 'pending@partners.test']);
    Affiliate::factory()->suspended()->create(['email' => 'off@partners.test']);

    $this->post(route('affiliate.login.store'), ['email' => 'pending@partners.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('identity::auth.pending')]);
    $this->post(route('affiliate.login.store'), ['email' => 'off@partners.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('identity::auth.inactive')]);
    $this->assertGuest('affiliate');
});

it('gives an affiliate without the panel role that role when it signs in (QA-FINAL-006)', function (): void {
    $affiliate = Affiliate::factory()->create(['email' => 'imported@partners.test']);
    expect($affiliate->hasRole('affiliate'))->toBeFalse();

    $this->post(route('affiliate.login.store'), ['email' => 'imported@partners.test', 'password' => 'password'])
        ->assertRedirect(route('affiliate.dashboard'));

    expect($affiliate->fresh()->hasRole('affiliate'))->toBeTrue();
    $this->get(route('affiliate.dashboard'))->assertOk();
    $this->get(route('affiliate.links'))->assertOk();
});

it('confirms the e-mail from a valid signed link and refuses an expired or tampered one', function (): void {
    $affiliate = Affiliate::factory()->pending()->create(['email' => 'new@partners.test', 'email_verified_at' => null]);
    $hash = sha1('new@partners.test');

    $this->get(URL::temporarySignedRoute('affiliate.verification.verify', now()->subMinute(), ['id' => $affiliate->id, 'hash' => $hash]))
        ->assertStatus(410)->assertSee(__('affiliates::panel.verify.expired_title'));
    $this->get(URL::temporarySignedRoute('affiliate.verification.verify', now()->addHour(), ['id' => $affiliate->id, 'hash' => sha1('other@x.test')]))
        ->assertStatus(410);
    expect($affiliate->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->get(URL::temporarySignedRoute('affiliate.verification.verify', now()->addHour(), ['id' => $affiliate->id, 'hash' => $hash]))
        ->assertOk()->assertSee(__('affiliates::panel.verify.done_pending'));
    expect($affiliate->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the confirmation link only to unconfirmed partners and answers the same for unknown e-mails', function (): void {
    Notification::fake();
    $pending = Affiliate::factory()->pending()->create(['email' => 'wait@partners.test', 'email_verified_at' => null]);
    $done = Affiliate::factory()->create(['email' => 'done@partners.test', 'email_verified_at' => now()]);

    foreach (['wait@partners.test', 'done@partners.test', 'nobody@partners.test'] as $email) {
        $this->from(route('affiliate.login'))->post(route('affiliate.verification.resend'), ['email' => $email])
            ->assertRedirect(route('affiliate.login'))->assertSessionHas('success', __('affiliates::panel.verify.resent'));
    }

    $this->post(route('affiliate.verification.resend'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    Notification::assertSentTo($pending, AffiliateVerifyEmailNotification::class);
    Notification::assertNotSentTo($done, AffiliateVerifyEmailNotification::class);
});

it('signs the store affiliate in once through an SSO link and refuses used, expired and invalid ones', function (): void {
    $tenant = affiliateTenant('Megz Store');
    $url = app(AffiliateSsoService::class)->issueFor($tenant);
    $affiliate = Affiliate::withoutTenantScope()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($affiliate->type)->toBe(AffiliateType::Vendor);

    $this->get($url)->assertRedirect(route('affiliate.dashboard'));
    $this->assertAuthenticatedAs($affiliate, 'affiliate');
    expect($affiliate->fresh()->hasRole('affiliate'))->toBeTrue();
    $this->get(route('affiliate.dashboard'))->assertOk();

    auth('affiliate')->logout();
    $this->get($url)->assertStatus(410)->assertSee(__('affiliates::panel.sso.used_title'));

    $expired = app(AffiliateSsoService::class)->issueFor($tenant);
    AffiliateSsoToken::query()->whereNull('used_at')->update(['expires_at' => now()->subSecond()]);
    $this->get($expired)->assertStatus(410)->assertSee(__('affiliates::panel.sso.expired_title'));

    $this->get(route('affiliate.sso', ['token' => str_repeat('a', 64)]))->assertStatus(410)->assertSee(__('affiliates::panel.sso.invalid_title'));
    $this->get(route('affiliate.sso', ['token' => 'short']))->assertStatus(410);
    $this->assertGuest('affiliate');
});

it('refuses the SSO link of a suspended store affiliate', function (): void {
    $tenant = affiliateTenant('Paused Store');
    $url = app(AffiliateSsoService::class)->issueFor($tenant);
    Affiliate::withoutTenantScope()->where('tenant_id', $tenant->id)->update(['status' => AccountStatus::Suspended->value]);

    $this->get($url)->assertStatus(410)->assertSee(__('affiliates::panel.sso.invalid_title'));
    $this->assertGuest('affiliate');
});
