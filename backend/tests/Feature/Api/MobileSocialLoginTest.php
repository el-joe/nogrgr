<?php

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Support\Social\CustomerIdTokenVerifier;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| Shopper social sign-in in the app (/api/mobile/v1/auth/social/{provider}): Google
| id_token through tokeninfo, Apple id_token signed by a JWKS key (RS256), audiences from
| the integration settings, account linking by verified e-mail, creation, inactive refusal,
| store isolation, and the bootstrap listing only configured providers.
*/

function appleTestKey(): array
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $key = ['private' => $private, 'jwk' => ['kty' => 'RSA', 'kid' => 'test-kid', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]];
    }

    return $key;
}

function appleIdToken(array $claims, string $kid = 'test-kid'): string
{
    $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $input = $b64(json_encode(['alg' => 'RS256', 'kid' => $kid])).'.'.$b64(json_encode($claims + ['iss' => 'https://appleid.apple.com', 'aud' => 'com.shop.app', 'exp' => time() + 600, 'iat' => time()]));
    openssl_sign($input, $signature, appleTestKey()['private'], OPENSSL_ALGO_SHA256);

    return $input.'.'.$b64($signature);
}

beforeEach(function (): void {
    config([
        'services.google.enabled' => true,
        'services.google.client_id' => 'web-client.apps.googleusercontent.com',
        'services.google.customer_client_ids' => "ios-client.apps.googleusercontent.com,\nandroid-client.apps.googleusercontent.com",
        'services.apple.enabled' => true,
        'services.apple.client_id' => 'com.shop.web',
        'services.apple.customer_client_ids' => 'com.shop.app',
    ]);
    $this->tenant = $this->createTenant('socialshop');
    $this->headers = mobileHeaders($this->tenant);
    // One fake for the whole test; each call of $this->google() swaps the tokeninfo answer.
    $this->tokeninfo = ['claims' => [], 'status' => 200];
    Http::fake([
        CustomerIdTokenVerifier::GOOGLE_TOKENINFO_URL.'*' => fn () => Http::response($this->tokeninfo['claims'] + [
            'iss' => 'https://accounts.google.com',
            'aud' => 'android-client.apps.googleusercontent.com',
            'sub' => 'google-sub-1',
            'email' => 'Sara@Example.test',
            'email_verified' => 'true',
            'name' => 'Sara Ali',
            'exp' => (string) (time() + 600),
        ], $this->tokeninfo['status']),
        CustomerIdTokenVerifier::APPLE_KEYS_URL => Http::response(['keys' => [appleTestKey()['jwk']]]),
    ]);
    $this->google = function (array $claims = [], int $status = 200): void {
        $this->tokeninfo = ['claims' => $claims, 'status' => $status];
    };
});

it('lists only configured providers in the bootstrap', function (): void {
    $this->getJson(mobileApi('bootstrap'), $this->headers)->assertOk()->assertJsonPath('data.auth.social_providers', ['google', 'apple']);

    config(['services.apple.enabled' => false, 'services.google.client_id' => '', 'services.google.customer_client_ids' => '']);
    $this->getJson(mobileApi('bootstrap'), $this->headers)->assertOk()->assertJsonPath('data.auth.social_providers', []);
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => 'x'], $this->headers)->assertNotFound()->assertJsonPath('success', false);
    $this->postJson(mobileApi('auth/social/facebook'), ['id_token' => 'x'], $this->headers)->assertNotFound();
});

it('validates the request', function (): void {
    $this->postJson(mobileApi('auth/social/google'), ['device_id' => 'bad id!'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id_token', 'device_id']);
});

it('creates a customer from a Google token and signs in again by the Google id', function (): void {
    ($this->google)();

    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 'google-token', 'device_id' => 'install-0001'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.customer.email', 'sara@example.test')
        ->assertJsonPath('data.customer.name', 'Sara Ali')
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token']]]);

    $this->tenant->run(function (): void {
        $customer = Customer::query()->where('email', 'sara@example.test')->firstOrFail();
        expect($customer->google_id)->toBe('google-sub-1')->and($customer->email_verified_at)->not->toBeNull();
    });

    Http::assertSent(fn ($request) => str_starts_with($request->url(), CustomerIdTokenVerifier::GOOGLE_TOKENINFO_URL) && $request['id_token'] === 'google-token');

    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 'google-token'], $this->headers)->assertOk();
    expect($this->tenant->run(fn () => Customer::query()->count()))->toBe(1);
});

it('links an existing account by verified e-mail and refuses unverified or foreign tokens', function (): void {
    $existing = $this->createCustomer($this->tenant, ['email' => 'sara@example.test', 'name' => 'Sara']);

    ($this->google)(['email_verified' => 'false']);
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('id_token');

    ($this->google)(['aud' => 'someone-else']);
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('id_token');

    ($this->google)(['exp' => (string) (time() - 10)]);
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertUnprocessable();

    ($this->google)([], 400);
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertUnprocessable();

    ($this->google)();
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertOk()->assertJsonPath('data.customer.id', $existing->id);
    expect($this->tenant->run(fn () => Customer::query()->find($existing->id)?->google_id))->toBe('google-sub-1');
});

it('refuses inactive accounts', function (): void {
    $this->createCustomer($this->tenant, ['email' => 'sara@example.test', 'status' => 'suspended']);
    ($this->google)();

    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('id_token');
});

it('verifies Apple tokens against the JWKS, with the nonce', function (): void {
    ($this->google)();

    $token = appleIdToken(['sub' => 'apple-sub-1', 'email' => 'relay@privaterelay.appleid.com', 'email_verified' => 'true', 'nonce' => hash('sha256', 'raw-nonce')]);
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => $token, 'nonce' => 'raw-nonce', 'name' => 'Omar'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.customer.name', 'Omar');
    expect($this->tenant->run(fn () => Customer::query()->where('apple_id', 'apple-sub-1')->exists()))->toBeTrue();

    // Wrong nonce, tampered signature, wrong audience, unknown key.
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => $token, 'nonce' => 'other'], $this->headers)->assertUnprocessable();
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => substr($token, 0, -4).'AAAA'], $this->headers)->assertUnprocessable();
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => appleIdToken(['sub' => 'x', 'aud' => 'com.other'])], $this->headers)->assertUnprocessable();
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => appleIdToken(['sub' => 'x'], 'unknown-kid')], $this->headers)->assertUnprocessable();

    // Later sign-ins without an e-mail match the Apple id.
    $this->postJson(mobileApi('auth/social/apple'), ['id_token' => appleIdToken(['sub' => 'apple-sub-1'])], $this->headers)->assertOk();
});

it('keeps social accounts inside their store', function (): void {
    ($this->google)();
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], $this->headers)->assertCreated();

    $other = $this->createTenant('socialother');
    $this->postJson(mobileApi('auth/social/google'), ['id_token' => 't'], mobileHeaders($other))->assertCreated();

    expect($this->tenant->run(fn () => Customer::query()->count()))->toBe(1)
        ->and($other->run(fn () => Customer::query()->count()))->toBe(1);
});
