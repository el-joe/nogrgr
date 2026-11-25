<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Support\Social\AppleOAuthClient;
use App\Modules\Identity\Support\Social\GoogleOAuthClient;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../Platform/Registration/SignUpHelpers.php';

/*
| WEB-016a: Google / Apple sign-up and sign-in for store owners — redirect with state,
| callbacks (GET Google, POST form_post Apple via the session handoff), owner created
| or linked, cancelled / invalid state, disabled providers, store picker for owners.
*/

beforeEach(function (): void {
    Notification::fake();
    config([
        'services.google' => ['enabled' => true, 'client_id' => 'google-client', 'client_secret' => 'google-secret'],
        'services.apple.enabled' => false,
    ]);
});

function fakeGoogle(array $user): void
{
    Http::fake([
        GoogleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'ya29.token', 'id_token' => 'x.y.z', 'expires_in' => 3599]),
        GoogleOAuthClient::USERINFO_URL => Http::response($user),
    ]);
}

/**
 * Starts the provider redirect and returns the state the provider would echo back.
 */
function socialState(string $provider = 'google', string $intent = 'register'): string
{
    $location = (string) test()->get(route('central.social.redirect', ['provider' => $provider, 'intent' => $intent]))->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return (string) $query['state'];
}

it('hides disabled providers behind a 404', function (): void {
    config(['services.google.enabled' => false]);

    $this->get(route('central.social.redirect', ['provider' => 'google']))->assertNotFound();
    $this->get(route('central.social.callback', ['provider' => 'google']))->assertNotFound();
    $this->get(route('central.social.redirect', ['provider' => 'apple']))->assertNotFound();
});

it('redirects to Google with a state and the fixed callback URL', function (): void {
    $location = (string) $this->get(route('central.social.redirect', ['provider' => 'google']))->headers->get('Location');

    expect($location)->toStartWith(GoogleOAuthClient::AUTHORIZE_URL)
        ->toContain('client_id=google-client')
        ->toContain('state=')
        ->toContain(urlencode(rtrim((string) config('app.url'), '/').'/auth/google/callback'));
});

it('signs up with Google: creates a verified owner and continues to the plan step', function (): void {
    fakeGoogle(['sub' => 'g-123', 'email' => 'Mona@Example.com', 'email_verified' => true, 'name' => 'Mona Ali']);

    $this->post(route('central.register.store'), signUpPayload(['email' => '', 'provider' => 'google']))
        ->assertRedirect(route('central.social.redirect', ['provider' => 'google', 'intent' => 'register']));
    $state = socialState();

    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'auth-code', 'state' => $state]))
        ->assertOk()
        ->assertSee('data-social-success', false)
        ->assertSee(route('central.register.social', ['provider' => 'google']), false);

    $owner = Owner::query()->sole();
    expect($owner->email)->toBe('mona@example.com')
        ->and($owner->google_id)->toBe('g-123')
        ->and($owner->password)->toBeNull()
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and(Auth::guard('owner')->id())->toBe($owner->id);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === GoogleOAuthClient::TOKEN_URL && $request['code'] === 'auth-code');

    $this->get(route('central.register.social', ['provider' => 'google']))->assertRedirect(route('central.register.plan'));
    $this->post(route('central.register.plan.store'), ['plan_id' => paidPlan(14)->id, 'term' => 'monthly', 'choice' => 'trial'])
        ->assertRedirect(route('central.register.continue'));

    $registration = VendorRegistration::query()->sole();
    expect($registration->owner_id)->toBe($owner->id)
        ->and($registration->phone)->toBe('+966501234567')
        ->and($registration->status)->toBe(RegistrationStatus::Incomplete)
        ->and($registration->meta['social_provider'])->toBe('google');
});

it('links Google to an existing owner with the same verified e-mail', function (): void {
    $owner = Owner::factory()->create(['email' => 'owner@example.com']);
    fakeGoogle(['sub' => 'g-777', 'email' => 'owner@example.com', 'email_verified' => true, 'name' => 'Someone']);

    $state = socialState(intent: 'login');
    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $state]))->assertOk()->assertSee('data-social-success', false);

    expect(Owner::query()->count())->toBe(1)
        ->and($owner->fresh()->google_id)->toBe('g-777')
        ->and(Auth::guard('owner')->id())->toBe($owner->id);
});

it('never links an unverified provider e-mail to an existing owner', function (): void {
    $owner = Owner::factory()->create(['email' => 'owner@example.com']);
    fakeGoogle(['sub' => 'g-888', 'email' => 'owner@example.com', 'email_verified' => false]);

    $state = socialState();
    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $state]))
        ->assertOk()
        ->assertSee(__('identity::social.unverified_email'));

    expect($owner->fresh()->google_id)->toBeNull()->and(Auth::guard('owner')->check())->toBeFalse();
});

it('shows the cancelled state when the visitor backs out', function (): void {
    $state = socialState();

    $this->get(route('central.social.callback', ['provider' => 'google', 'error' => 'access_denied', 'state' => $state]))
        ->assertOk()
        ->assertSee(__('identity::social.title.cancelled', ['provider' => 'Google']));

    expect(Owner::query()->count())->toBe(0);
});

it('refuses a forged or replayed state', function (): void {
    fakeGoogle(['sub' => 'g-1', 'email' => 'x@example.com', 'email_verified' => true]);
    $state = socialState();

    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => 'forged']))
        ->assertOk()->assertSee(__('identity::social.title.error', ['provider' => 'Google']));
    // The pending state is single-use: the real one no longer works either.
    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $state]))
        ->assertOk()->assertSee(__('identity::social.title.error', ['provider' => 'Google']));

    expect(Owner::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('signs up with Apple through the form_post handoff', function (): void {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    config(['services.apple' => ['enabled' => true, 'client_id' => 'com.nogrgr.web', 'team_id' => 'TEAM123', 'key_id' => 'KEY123', 'private_key' => $pem]]);

    $location = (string) $this->get(route('central.social.redirect', ['provider' => 'apple']))->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $state = $query['state'];
    $nonce = $query['nonce'];

    $claims = ['iss' => AppleOAuthClient::ISSUER, 'aud' => 'com.nogrgr.web', 'exp' => time() + 600, 'sub' => 'apple-001', 'email' => 'relay@privaterelay.appleid.com', 'email_verified' => 'true', 'nonce' => $nonce];
    $idToken = 'eyJhbGciOiJSUzI1NiJ9.'.rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=').'.signature';
    Http::fake([AppleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'a', 'id_token' => $idToken])]);

    $handoff = $this->post(route('central.social.callback.post', ['provider' => 'apple']), [
        'code' => 'apple-code',
        'state' => $state,
        'user' => json_encode(['name' => ['firstName' => 'Sara', 'lastName' => 'Hassan']]),
    ]);
    $handoff->assertStatus(303);

    $this->get((string) $handoff->headers->get('Location'))->assertOk()->assertSee('data-social-success', false);

    $owner = Owner::query()->sole();
    expect($owner->apple_id)->toBe('apple-001')->and($owner->name)->toBe('Sara Hassan');

    Http::assertSent(function (HttpRequest $request): bool {
        $secret = explode('.', (string) $request['client_secret']);

        return $request->url() === AppleOAuthClient::TOKEN_URL && count($secret) === 3 && $request['code'] === 'apple-code';
    });
});

it('rejects an Apple id_token issued for another client', function (): void {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    config(['services.apple' => ['enabled' => true, 'client_id' => 'com.nogrgr.web', 'team_id' => 'T', 'key_id' => 'K', 'private_key' => $pem]]);

    $state = socialState('apple');
    $claims = ['iss' => AppleOAuthClient::ISSUER, 'aud' => 'com.attacker', 'exp' => time() + 600, 'sub' => 'apple-x', 'email' => 'x@example.com'];
    Http::fake([AppleOAuthClient::TOKEN_URL => Http::response(['id_token' => 'h.'.rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=').'.s'])]);

    $handoff = $this->post(route('central.social.callback.post', ['provider' => 'apple']), ['code' => 'c', 'state' => $state]);
    $this->get((string) $handoff->headers->get('Location'))->assertOk()->assertSee(__('identity::social.title.error', ['provider' => 'Apple']));

    expect(Owner::query()->count())->toBe(0);
});

it('signs an owner with stores in and opens one from the store picker', function (): void {
    $owner = Owner::factory()->create(['email' => 'boss@example.test', 'google_id' => 'g-boss']);
    $acme = $this->createTenant('acme', ['name' => 'Acme Store', 'owner_id' => $owner->id]);
    $beta = $this->createTenant('beta', ['name' => 'Beta Store', 'owner_id' => $owner->id]);
    $this->createTenant('other', ['name' => 'Other Store']);
    $this->createVendorOwner($acme, ['email' => 'boss@example.test']);
    $this->createVendorOwner($beta, ['email' => 'boss@example.test']);
    fakeGoogle(['sub' => 'g-boss', 'email' => 'boss@example.test', 'email_verified' => true]);

    $state = socialState(intent: 'login');
    $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'c', 'state' => $state]))
        ->assertOk()
        ->assertSee(route('central.tenant.stores'), false);

    $this->get(route('central.tenant.stores'))->assertRedirect(route('owner.select-store'));

    $this->get(route('owner.select-store'))
        ->assertOk()
        ->assertSee('Acme Store')
        ->assertSee('Beta Store')
        ->assertDontSee('Other Store');

    $this->post(route('central.tenant.stores.select'), ['tenant_id' => $beta->id])
        ->assertRedirect($this->centralPanelUrl($beta, 'dashboard'));

    expect(Auth::guard('vendor')->check())->toBeTrue();
});

it('opens the owner session on a central password login of an owned store', function (): void {
    $owner = Owner::factory()->create(['email' => 'boss@example.test']);
    $acme = $this->createTenant('acme', ['owner_id' => $owner->id]);
    $this->createVendorOwner($acme, ['email' => 'boss@example.test']);

    $this->post(route('central.tenant.login.store'), ['email' => 'boss@example.test', 'password' => 'password'])
        ->assertRedirect($this->centralPanelUrl($acme, 'dashboard'));

    expect(Auth::guard('owner')->id())->toBe($owner->id);

    $this->get(route('owner.stores.create'))->assertOk();
});
