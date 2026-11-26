<?php

use App\Modules\Api\Notifications\CustomerPasswordResetCodeNotification;
use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Notifications\CustomerEmailCodeNotification;
use App\Modules\Notifications\Models\CustomerDevice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| Mobile API v1 customer auth (APP-020 … APP-023): Sanctum access + refresh tokens per app
| install, sign-up with intl phone, sign-in throttling, refresh rotation, sign-out, reset
| password with an e-mailed code, e-mail confirmation code, store isolation of tokens and
| the {success: false, message} error envelope.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('shop');
    $this->customer = $this->createCustomer($this->tenant, ['email' => 'sara@example.test', 'name' => 'Sara']);
});

it('signs in with e-mail and password and returns an access + refresh pair', function (): void {
    $response = $this->postJson(mobileApi('auth/login'), ['email' => 'SARA@example.test', 'password' => 'password', 'device_id' => 'install-0001'], mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.customer.email', 'sara@example.test')
        ->assertJsonPath('data.tokens.token_type', 'Bearer');

    $access = $response->json('data.tokens.access_token');

    $this->getJson(mobileApi('me'), mobileHeaders($this->tenant, $access))
        ->assertOk()
        ->assertJsonPath('data.customer.id', $this->customer->id);

    $this->tenant->run(function (): void {
        expect($this->customer->tokens()->where('name', 'mobile:install-0001')->count())->toBe(2);
    });
});

it('refuses wrong credentials with one generic message and throttles after five tries', function (): void {
    RateLimiter::clear('customer-api-login:'.$this->tenant->id.':'.sha1('sara@example.test|127.0.0.1'));

    $this->postJson(mobileApi('auth/login'), ['email' => 'sara@example.test', 'password' => 'wrong-pass'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);

    $this->postJson(mobileApi('auth/login'), ['email' => 'nobody@example.test', 'password' => 'wrong-pass'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);

    foreach (range(1, 4) as $attempt) {
        $this->postJson(mobileApi('auth/login'), ['email' => 'sara@example.test', 'password' => 'wrong-pass'], mobileHeaders($this->tenant));
    }

    $this->postJson(mobileApi('auth/login'), ['email' => 'sara@example.test', 'password' => 'password'], mobileHeaders($this->tenant))
        ->assertStatus(429);
});

it('refuses a suspended account after the password matched', function (): void {
    $this->createCustomer($this->tenant, ['email' => 'blocked@example.test', 'status' => 'suspended']);

    $this->postJson(mobileApi('auth/login'), ['email' => 'blocked@example.test', 'password' => 'password'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('validates the sign-in payload', function (): void {
    $this->postJson(mobileApi('auth/login'), ['email' => 'not-an-email', 'device_id' => 'x'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_id']);
});

it('registers a customer with an intl phone, sends the e-mail code and binds the install', function (): void {
    Event::fake([CustomerRegistered::class]);
    Notification::fake();
    $this->tenant->run(fn () => CustomerDevice::factory()->create(['device_id' => 'install-0002']));

    $this->postJson(mobileApi('auth/register'), [
        'name' => 'Omar',
        'email' => 'omar@example.test',
        'phone' => '050 123 4567',
        'phone_country' => 'sa',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'device_id' => 'install-0002',
    ], mobileHeaders($this->tenant, extra: ['X-Locale' => 'ar']))
        ->assertCreated()
        ->assertJsonPath('data.customer.email', 'omar@example.test')
        ->assertJsonPath('data.customer.phone_country', 'SA')
        ->assertJsonPath('data.customer.locale', 'ar')
        ->assertJsonPath('data.customer.email_verified', false)
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'refresh_token', 'expires_at']]]);

    Event::assertDispatched(CustomerRegistered::class);

    $this->tenant->run(function (): void {
        $customer = Customer::query()->where('email', 'omar@example.test')->sole();
        Notification::assertSentTo($customer, CustomerEmailCodeNotification::class);
        expect(CustomerDevice::query()->where('device_id', 'install-0002')->value('customer_id'))->toBe($customer->id);
    });
});

it('validates registration (unique e-mail, phone for the country, confirmed password)', function (): void {
    $this->postJson(mobileApi('auth/register'), [
        'name' => '',
        'email' => 'sara@example.test',
        'phone' => '123',
        'phone_country' => 'SA',
        'password' => 'short',
        'password_confirmation' => 'other',
    ], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
});

it('rotates the token pair on refresh and refuses the old refresh token and access use of a refresh token', function (): void {
    $tokens = mobileLogin($this->tenant, 'sara@example.test', deviceId: 'install-0003');

    $this->getJson(mobileApi('me'), mobileHeaders($this->tenant, $tokens['refresh_token']))->assertUnauthorized();
    $this->getJson('/store-api/v1/orders', mobileHeaders($this->tenant, $tokens['refresh_token']))->assertStatus(401);

    $new = $this->postJson(mobileApi('auth/refresh'), ['refresh_token' => $tokens['refresh_token']], mobileHeaders($this->tenant))
        ->assertOk()
        ->json('data.tokens');

    expect($new['access_token'])->not->toBe($tokens['access_token']);

    $this->postJson(mobileApi('auth/refresh'), ['refresh_token' => $tokens['refresh_token']], mobileHeaders($this->tenant))
        ->assertUnauthorized()
        ->assertJsonPath('success', false);

    $this->postJson(mobileApi('auth/refresh'), ['refresh_token' => $new['access_token']], mobileHeaders($this->tenant))->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->getJson(mobileApi('me'), mobileHeaders($this->tenant, $new['access_token']))->assertOk();
});

it('refuses an expired refresh token', function (): void {
    $tokens = mobileLogin($this->tenant, 'sara@example.test');
    $this->tenant->run(fn () => $this->customer->tokens()->update(['expires_at' => now()->subMinute()]));

    $this->postJson(mobileApi('auth/refresh'), ['refresh_token' => $tokens['refresh_token']], mobileHeaders($this->tenant))->assertUnauthorized();
});

it('signs out this install only, or every install', function (): void {
    $first = mobileLogin($this->tenant, 'sara@example.test', deviceId: 'install-000a');
    $second = mobileLogin($this->tenant, 'sara@example.test', deviceId: 'install-000b');

    $this->postJson(mobileApi('auth/logout'), [], mobileHeaders($this->tenant, $first['access_token']))->assertOk();

    $this->tenant->run(function (): void {
        expect($this->customer->tokens()->pluck('name')->unique()->values()->all())->toBe(['mobile:install-000b']);
    });

    $this->app['auth']->forgetGuards();
    $this->postJson(mobileApi('auth/logout'), ['everywhere' => true], mobileHeaders($this->tenant, $second['access_token']))->assertOk();

    $this->tenant->run(fn () => expect($this->customer->tokens()->count())->toBe(0));
});

it('keeps tokens inside their store', function (): void {
    $other = $this->createTenant('other');
    $tokens = mobileLogin($this->tenant, 'sara@example.test');

    $this->getJson(mobileApi('me'), mobileHeaders($other, $tokens['access_token']))
        ->assertUnauthorized()
        ->assertJson(['success' => false, 'message' => __('api::mobile.unauthenticated')]);
});

it('answers an unknown store with the error envelope', function (): void {
    $this->getJson(mobileApi('bootstrap'), ['X-Tenant' => '999999', 'Accept' => 'application/json'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('requires a token on customer routes', function (string $method, string $path): void {
    $this->json(strtoupper($method), mobileApi($path), [], mobileHeaders($this->tenant))
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
})->with([
    'me' => ['get', 'me'],
    'logout' => ['post', 'auth/logout'],
    'verification' => ['get', 'auth/email/verification'],
    'profile' => ['put', 'account/profile'],
    'password' => ['put', 'account/password'],
    'preferences' => ['get', 'account/notification-preferences'],
    'delete' => ['delete', 'account'],
    'addresses' => ['get', 'account/addresses'],
    'favorites' => ['get', 'favorites'],
    'notifications' => ['get', 'notifications'],
    'devices' => ['get', 'devices'],
]);

it('resets the password with the e-mailed code and revokes every token', function (): void {
    Notification::fake();
    $tokens = mobileLogin($this->tenant, 'sara@example.test');
    $code = null;

    $this->postJson(mobileApi('auth/password/forgot'), ['email' => 'sara@example.test'], mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->tenant->run(function () use (&$code): void {
        Notification::assertSentTo($this->customer, CustomerPasswordResetCodeNotification::class, function (CustomerPasswordResetCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });
    });

    $this->postJson(mobileApi('auth/password/check'), ['email' => 'sara@example.test', 'code' => $code === '000000' ? '111111' : '000000'], mobileHeaders($this->tenant))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->postJson(mobileApi('auth/password/check'), ['email' => 'sara@example.test', 'code' => $code], mobileHeaders($this->tenant))->assertOk();

    $this->postJson(mobileApi('auth/password/reset'), ['email' => 'sara@example.test', 'code' => $code, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'], mobileHeaders($this->tenant))
        ->assertOk();

    $this->tenant->run(fn () => expect($this->customer->tokens()->count())->toBe(0));

    $this->postJson(mobileApi('auth/password/reset'), ['email' => 'sara@example.test', 'code' => $code, 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'], mobileHeaders($this->tenant))
        ->assertUnprocessable();

    $this->app['auth']->forgetGuards();
    mobileLogin($this->tenant, 'sara@example.test', 'brand-new-pass');
    $this->getJson(mobileApi('me'), mobileHeaders($this->tenant, $tokens['access_token']))->assertUnauthorized();
});

it('never reveals whether a reset e-mail exists', function (): void {
    Notification::fake();

    $this->postJson(mobileApi('auth/password/forgot'), ['email' => 'nobody@example.test'], mobileHeaders($this->tenant))
        ->assertOk()
        ->assertJsonPath('message', __st('mobile.reset.sent'));

    Notification::assertNothingSent();
});

it('confirms the e-mail with the 6-digit code', function (): void {
    Notification::fake();
    $tokens = mobileLogin($this->tenant, 'sara@example.test');
    $code = null;

    $this->postJson(mobileApi('auth/email/resend'), [], mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertOk()
        ->assertJsonPath('data.verified', false);

    $this->tenant->run(function () use (&$code): void {
        Notification::assertSentTo($this->customer, CustomerEmailCodeNotification::class, function (CustomerEmailCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });
    });

    $this->postJson(mobileApi('auth/email/verify'), ['code' => 'abc'], mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->postJson(mobileApi('auth/email/verify'), ['code' => $code], mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    $this->getJson(mobileApi('auth/email/verification'), mobileHeaders($this->tenant, $tokens['access_token']))
        ->assertOk()
        ->assertJsonPath('data.verified', true);
});
