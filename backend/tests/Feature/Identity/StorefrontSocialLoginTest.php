<?php

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Support\Social\CustomerSocialHandoff;
use App\Modules\Identity\Support\Social\GoogleOAuthClient;
use Illuminate\Support\Facades\Http;

/*
| Theme "Continue with Google / Apple" for shoppers: buttons only for configured providers,
| store → platform hand-off (sealed payload), platform callback handing the verified profile
| back to the store, sign-in / account creation on the store, other-store tokens refused,
| cancelled and failed attempts.
*/

beforeEach(function (): void {
    config([
        'services.google' => ['enabled' => true, 'client_id' => 'google-client', 'client_secret' => 'google-secret'],
        'services.apple.enabled' => false,
    ]);
    $this->tenant = $this->createTenant('sfsocial');
    $this->url = fn (string $path): string => $this->tenantUrl($this->tenant, $path);
    $this->profile = new SocialProfileData(SocialProvider::Google, 'g-777', 'lina@example.test', true, 'Lina Saad');
});

it('shows only the configured providers on the sign-in and sign-up pages', function (): void {
    $this->get(($this->url)('/account/login'))->assertOk()->assertSee('data-social-provider="google"', false)->assertDontSee('data-social-provider="apple"', false);
    $this->get(($this->url)('/account/register'))->assertOk()->assertSee('data-social-provider="google"', false);

    config(['services.google.enabled' => false]);
    $this->get(($this->url)('/account/login'))->assertOk()->assertDontSee('data-social-login', false);
    $this->get(($this->url)('/account/social/google'))->assertNotFound();
    $this->get(($this->url)('/account/social/apple'))->assertNotFound();
});

it('sends the shopper to the platform with a sealed store payload', function (): void {
    $location = (string) $this->get(($this->url)('/account/social/google?redirect=/account/orders'))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith(route('central.social.redirect', ['provider' => 'google']))->toContain('intent=customer');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $start = CustomerSocialHandoff::readStart($query['payload']);
    expect($start['tenant_id'])->toBe($this->tenant->id)->and($start['redirect'])->toEndWith('/account/orders');

    // The platform starts the provider flow only for a valid payload.
    $this->get(route('central.social.redirect', ['provider' => 'google', 'intent' => 'customer', 'payload' => 'forged']))->assertNotFound();
    expect((string) $this->get($location)->headers->get('Location'))->toStartWith(GoogleOAuthClient::AUTHORIZE_URL);
});

it('hands the verified profile back from the platform callback', function (): void {
    Http::fake([
        GoogleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'ya29.token', 'id_token' => 'x.y.z', 'expires_in' => 3599]),
        GoogleOAuthClient::USERINFO_URL => Http::response(['sub' => 'g-777', 'email' => 'lina@example.test', 'email_verified' => true, 'name' => 'Lina Saad']),
    ]);
    $start = (string) $this->get(($this->url)('/account/social/google'))->headers->get('Location');
    $provider = (string) $this->get($start)->headers->get('Location');
    parse_str((string) parse_url($provider, PHP_URL_QUERY), $query);

    $back = (string) $this->get(route('central.social.callback', ['provider' => 'google', 'code' => 'auth-code', 'state' => $query['state']]))->assertRedirect()->headers->get('Location');
    expect($back)->toStartWith(($this->url)('/account/social/complete'));

    $this->get($back)->assertRedirect();
    $this->assertAuthenticated('customer');
    $this->tenant->run(fn () => expect(Customer::query()->where('google_id', 'g-777')->where('email', 'lina@example.test')->exists())->toBeTrue());
});

it('signs in or creates the customer from the hand-back and returns to the target', function (): void {
    $token = CustomerSocialHandoff::result(['tenant_id' => $this->tenant->id, 'base' => ($this->url)(''), 'redirect' => '/account/orders'], $this->profile);

    $this->get(($this->url)('/account/social/complete?token='.urlencode($token)))->assertRedirect('/account/orders');
    $this->assertAuthenticated('customer');
    expect($this->tenant->run(fn () => Customer::query()->where('google_id', 'g-777')->first()?->email_verified_at))->not->toBeNull();
});

it('refuses tokens of another store, forged and cancelled attempts', function (): void {
    $other = $this->createTenant('sfsocialother');
    $foreign = CustomerSocialHandoff::result(['tenant_id' => $other->id, 'base' => 'http://x', 'redirect' => null], $this->profile);

    $this->get(($this->url)('/account/social/complete?token='.urlencode($foreign)))->assertRedirect()->assertSessionHas('social_error');
    $this->get(($this->url)('/account/social/complete?token=forged'))->assertSessionHas('social_error');

    $cancelled = CustomerSocialHandoff::result(['tenant_id' => $this->tenant->id, 'base' => 'http://x', 'redirect' => null], null, 'cancelled');
    $this->get(($this->url)('/account/social/complete?token='.urlencode($cancelled)))->assertSessionMissing('social_error');

    $this->assertGuest('customer');
    $this->tenant->run(fn () => expect(Customer::query()->count())->toBe(0));
});

it('refuses inactive customers with a message on the sign-in page', function (): void {
    $this->createCustomer($this->tenant, ['email' => 'lina@example.test', 'status' => 'suspended']);
    $token = CustomerSocialHandoff::result(['tenant_id' => $this->tenant->id, 'base' => 'http://x', 'redirect' => null], $this->profile);

    $this->get(($this->url)('/account/social/complete?token='.urlencode($token)))->assertSessionHas('social_error', __('identity::auth.inactive'));
    $this->assertGuest('customer');
});
