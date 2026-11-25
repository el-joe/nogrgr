<?php

use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Services\SessionAuthService;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
| Storefront customer auth on the store host: /account/login, /account/register,
| /account/forgot-password, /account/reset-password/{token}, POST /account/logout.
*/

it('renders the sign-in and registration pages with a safe redirect only', function (): void {
    $tenant = $this->createTenant('acme');

    $this->get($this->tenantUrl($tenant, '/account/login?redirect=/cart'))
        ->assertOk()
        ->assertSee('name="redirect" value="http://acme.localhost/cart"', false)
        ->assertSee('href="http://acme.localhost/account/forgot-password"', false);

    $this->get($this->tenantUrl($tenant, '/account/login?redirect=https://evil.test/phish'))
        ->assertOk()
        ->assertDontSee('evil.test');

    $this->get($this->tenantUrl($tenant, '/account/register'))
        ->assertOk()
        ->assertSee('name="phone_country"', false);
});

it('registers a customer, signs them in and notifies the store owners', function (): void {
    $tenant = $this->createTenant('acme');
    $owner = $this->createVendorOwner($tenant);

    $this->withUnencryptedCookie('locale', 'ar')
        ->post($this->tenantUrl($tenant, '/account/register'), [
            'name' => 'Sara Ali',
            'email' => 'Sara@Example.test',
            'phone' => '050 123 4567',
            'phone_country' => 'sa',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])
        ->assertRedirect($this->tenantUrl($tenant, '/account/profile'));

    $this->initializeTenancy($tenant);
    $customer = Customer::query()->sole();

    expect($customer->email)->toBe('sara@example.test')
        ->and($customer->phone)->toBe('+966501234567')
        ->and($customer->phone_country)->toBe('SA')
        ->and($customer->locale)->toBe('ar')
        ->and(Auth::guard('customer')->id())->toBe($customer->id)
        ->and(session(EnsureCustomerSessionTenant::SESSION_KEY))->toBe($tenant->id)
        ->and($owner->fresh()->notifications()->sole()->data)->toMatchArray(['type' => 'customer.registered', 'customer_id' => $customer->id])
        ->and($owner->fresh()->notifications()->sole()->type)->toBe('customer.registered')
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::Registered->value)->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('fires the CustomerRegistered event once with the tenant id', function (): void {
    Event::fake([CustomerRegistered::class]);
    $tenant = $this->createTenant('acme');

    $this->postJson($this->tenantUrl($tenant, '/account/register'), [
        'name' => 'Omar', 'email' => 'omar@example.test', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
    ])->assertCreated()->assertJson(['success' => true, 'redirect' => $this->tenantUrl($tenant, '/account/profile')]);

    Event::assertDispatchedTimes(CustomerRegistered::class, 1);
    Event::assertDispatched(CustomerRegistered::class, fn (CustomerRegistered $event): bool => $event->tenantId === $tenant->id);
});

it('validates registration', function (array $input, string $field): void {
    $tenant = $this->createTenant('acme');
    $this->createCustomer($tenant, ['email' => 'taken@example.test', 'phone' => '+966501234567']);

    $this->postJson($this->tenantUrl($tenant, '/account/register'), array_merge([
        'name' => 'Sara', 'email' => 'new@example.test', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
    ], $input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'name required' => [['name' => ''], 'name'],
    'name max' => [['name' => str_repeat('a', 121)], 'name'],
    'email required' => [['email' => ''], 'email'],
    'email format' => [['email' => 'nope'], 'email'],
    'email unique' => [['email' => 'TAKEN@example.test'], 'email'],
    'password min 8' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'password confirmed' => [['password_confirmation' => 'different-pass'], 'password'],
    'phone invalid for country' => [['phone' => '12345', 'phone_country' => 'SA'], 'phone'],
    'phone of another country' => [['phone' => '+971501234567', 'phone_country' => 'SA'], 'phone'],
    'phone country required with phone' => [['phone' => '0501234567'], 'phone_country'],
    'phone unique' => [['phone' => '0501234567', 'phone_country' => 'SA'], 'phone'],
]);

it('signs a customer in and redirects to a same-host target only', function (string $redirect, string $expected): void {
    $tenant = $this->createTenant('acme');
    $this->createCustomer($tenant, ['email' => 'sara@example.test']);

    $this->post($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'password', 'redirect' => $redirect])
        ->assertRedirect($expected === '{store}' ? rtrim($this->tenantUrl($tenant, ''), '/') : str_replace('{store}', $this->tenantUrl($tenant, ''), $expected));

    expect(Auth::guard('customer')->check())->toBeTrue();
})->with([
    'relative path' => ['/cart?step=2', '{store}cart?step=2'],
    'absolute same host' => ['http://acme.localhost/account/orders', '{store}account/orders'],
    'other host' => ['https://evil.test/phish', '{store}'],
    'protocol relative' => ['//evil.test', '{store}'],
    'backslash trick' => ['/\\evil.test', '{store}'],
    'javascript scheme' => ['javascript:alert(1)', '{store}'],
    'credentials in url' => ['http://acme.localhost@evil.test/', '{store}'],
    'other store' => ['http://beta.localhost/account', '{store}'],
]);

it('answers JSON logins with the redirect in the contract', function (): void {
    $tenant = $this->createTenant('acme');
    $this->createCustomer($tenant, ['email' => 'sara@example.test']);

    $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'password', 'redirect' => '//evil.test'])
        ->assertOk()
        ->assertJson(['success' => true, 'redirect' => rtrim($this->tenantUrl($tenant, '/'), '/')]);
});

it('rejects wrong credentials, inactive accounts and throttles', function (): void {
    $tenant = $this->createTenant('acme');
    $this->createCustomer($tenant, ['email' => 'sara@example.test']);
    $this->createCustomer($tenant, ['email' => 'off@example.test', 'status' => 'suspended']);

    $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => __('auth.failed')]);

    $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'off@example.test', 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => __('identity::auth.inactive')]);

    foreach (range(2, SessionAuthService::MAX_ATTEMPTS) as $attempt) {
        $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'wrong'.$attempt]);
    }

    $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'password'])
        ->assertStatus(429);

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('does not let a customer of store A sign in on store B', function (): void {
    $acme = $this->createTenant('acme');
    $beta = $this->createTenant('beta');
    $this->createCustomer($acme, ['email' => 'sara@example.test']);

    $this->postJson($this->tenantUrl($beta, '/account/login'), ['email' => 'sara@example.test', 'password' => 'password'])
        ->assertUnprocessable();
});

it('treats a session bound to another store as a guest', function (): void {
    $acme = $this->createTenant('acme');
    $beta = $this->createTenant('beta');
    $customer = $this->createCustomer($acme);
    $this->createCustomer($beta);

    $this->actingAsCustomer($customer, $acme)
        ->get($this->tenantUrl($beta, '/account/profile'))
        ->assertRedirect($this->tenantUrl($beta, '/account/login'));
});

it('signs out', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->post($this->tenantUrl($tenant, '/account/logout'))
        ->assertRedirect(rtrim($this->tenantUrl($tenant, '/'), '/'));

    expect(Auth::guard('customer')->check())->toBeFalse();

    $this->post($this->tenantUrl($tenant, '/account/logout'))->assertRedirect($this->tenantUrl($tenant, '/account/login'));
});

it('resets a forgotten password with a link on the store host', function (): void {
    Notification::fake();
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['email' => 'sara@example.test', 'locale' => 'ar']);

    $this->get($this->tenantUrl($tenant, '/account/forgot-password'))->assertOk();

    $this->postJson($this->tenantUrl($tenant, '/account/forgot-password'), ['email' => 'sara@example.test'])
        ->assertOk()->assertJson(['success' => true, 'message' => __('identity::auth.reset.link_sent')]);
    $this->postJson($this->tenantUrl($tenant, '/account/forgot-password'), ['email' => 'nobody@example.test'])
        ->assertOk()->assertJson(['message' => __('identity::auth.reset.link_sent')]);

    $url = null;
    Notification::assertSentTo($customer, ResetPasswordNotification::class, function (ResetPasswordNotification $notification, array $channels, object $notifiable, ?string $locale) use (&$url): bool {
        $url = $notification->url;

        return $locale === 'ar';
    });
    Notification::assertSentTimes(ResetPasswordNotification::class, 1);

    expect($url)->toStartWith($this->tenantUrl($tenant, '/account/reset-password/'));
    $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

    $this->get((string) $url)->assertOk()->assertSee('value="sara@example.test"', false);

    $this->postJson($this->tenantUrl($tenant, '/account/reset-password'), [
        'token' => $token, 'email' => 'sara@example.test', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
    ])->assertOk()->assertJson(['redirect' => $this->tenantUrl($tenant, '/account/login')]);

    $this->initializeTenancy($tenant);
    expect(Hash::check('brand-new-1', (string) $customer->fresh()->password))->toBeTrue();
});

it('saves the language picked with ?lang= and applies it on later visits', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['locale' => 'en']);

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/profile?lang=ar'))
        ->assertOk();

    $this->initializeTenancy($tenant);
    $customer = $customer->fresh();
    expect($customer->locale)->toBe('ar');
    $this->endTenancy();

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/profile'))
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false);
});
