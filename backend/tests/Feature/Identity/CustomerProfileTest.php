<?php

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Services\CustomerProfileService;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/*
| Account › Profile (/account/profile), password, delete account and
| notification preferences (/account/notifications).
*/

it('redirects guests to the customer login', function (string $method, string $path): void {
    $tenant = $this->createTenant('acme');

    $this->{$method}($this->tenantUrl($tenant, $path))->assertRedirect($this->tenantUrl($tenant, '/account/login'));
    $this->{$method.'Json'}($this->tenantUrl($tenant, $path))->assertUnauthorized()->assertJson(['redirect' => $this->tenantUrl($tenant, '/account/login')]);
})->with([
    'profile page' => ['get', '/account/profile'],
    'profile update' => ['put', '/account/profile'],
    'password' => ['put', '/account/password'],
    'delete' => ['delete', '/account/profile'],
    'notifications page' => ['get', '/account/notifications'],
    'notifications update' => ['put', '/account/notifications'],
]);

it('shows and updates the profile', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['name' => 'Sara', 'email' => 'sara@example.test', 'email_verified_at' => now()]);

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/profile'))
        ->assertOk()
        ->assertSee('value="sara@example.test"', false);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/profile'), [
            'name' => 'Sara Ali', 'email' => 'NEW@example.test', 'phone' => '0501234567', 'phone_country' => 'sa', 'locale' => 'ar',
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'data' => ['email' => 'new@example.test', 'phone' => '+966501234567', 'phone_country' => 'SA', 'locale' => 'ar']])
        ->assertCookie('locale', 'ar', false);

    $this->initializeTenancy($tenant);
    $fresh = $customer->fresh();

    expect($fresh->name)->toBe('Sara Ali')
        ->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->locale)->toBe('ar');
});

it('validates the profile', function (array $input, string $field): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['email' => 'sara@example.test']);
    $this->createCustomer($tenant, ['email' => 'other@example.test', 'phone' => '+971501234567']);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/profile'), array_merge(['name' => 'Sara', 'email' => 'sara@example.test'], $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'name required' => [['name' => ''], 'name'],
    'email unique' => [['email' => 'other@example.test'], 'email'],
    'email format' => [['email' => 'x'], 'email'],
    'phone invalid' => [['phone' => '123', 'phone_country' => 'AE'], 'phone'],
    'phone unique' => [['phone' => '0501234567', 'phone_country' => 'AE'], 'phone'],
    'locale supported' => [['locale' => 'xx'], 'locale'],
]);

it('keeps the own email and phone valid on update', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['email' => 'sara@example.test', 'phone' => '+966501234567', 'phone_country' => 'SA']);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/profile'), ['name' => 'Sara', 'email' => 'sara@example.test', 'phone' => '+966501234567', 'phone_country' => 'SA'])
        ->assertOk();
});

it('changes the password with the current one', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/password'), ['current_password' => 'wrong', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/password'), ['current_password' => 'password', 'password' => 'brand-new-1', 'password_confirmation' => 'other'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/password'), ['current_password' => 'password', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
        ->assertOk()->assertJson(['success' => true]);

    $this->initializeTenancy($tenant);

    expect(Hash::check('brand-new-1', (string) $customer->fresh()->password))->toBeTrue()
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(AuditEvent::withoutTenantScope()->where('action', AuditAction::PasswordChanged->value)->exists())->toBeTrue();
});

it('deletes the account after the password confirmation: anonymised and soft deleted', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant, ['email' => 'sara@example.test', 'phone' => '+966501234567']);
    $this->initializeTenancy($tenant);
    CustomerAddress::factory()->for($customer)->create();
    $customer->createToken('mobile');
    $this->endTenancy();

    $this->actingAsCustomer($customer, $tenant)
        ->deleteJson($this->tenantUrl($tenant, '/account/profile'), ['password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->actingAsCustomer($customer, $tenant)
        ->deleteJson($this->tenantUrl($tenant, '/account/profile'), ['password' => 'password'])
        ->assertOk()->assertJson(['success' => true, 'redirect' => rtrim($this->tenantUrl($tenant, '/'), '/')]);

    $this->initializeTenancy($tenant);
    $deleted = Customer::withTrashed()->findOrFail($customer->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->name)->toBe(CustomerProfileService::ANONYMISED_NAME)
        ->and($deleted->email)->toBeNull()
        ->and($deleted->phone)->toBeNull()
        ->and($deleted->password)->toBeNull()
        ->and(CustomerAddress::query()->count())->toBe(0)
        ->and($deleted->tokens()->count())->toBe(0)
        ->and(Auth::guard('customer')->check())->toBeFalse();

    $this->endTenancy();

    $this->postJson($this->tenantUrl($tenant, '/account/login'), ['email' => 'sara@example.test', 'password' => 'password'])->assertUnprocessable();

    $this->postJson($this->tenantUrl($tenant, '/account/register'), [
        'name' => 'Sara', 'email' => 'sara@example.test', 'phone' => '+966501234567', 'phone_country' => 'SA',
        'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
    ])->assertCreated();
});

it('shows and stores notification preferences', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/notifications'))
        ->assertOk()
        ->assertSee('name="preferences[order_updates][email]"', false);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/notifications'), ['preferences' => [
            'order_updates' => ['email' => false],
            'promotions' => ['email' => true, 'push' => true],
        ]])
        ->assertOk()
        ->assertJsonPath('data.preferences.order_updates.email', false)
        ->assertJsonPath('data.preferences.order_updates.push', true)
        ->assertJsonPath('data.preferences.promotions.email', true)
        ->assertJsonPath('data.preferences.returns.email', true);

    $this->initializeTenancy($tenant);
    expect($customer->fresh()->notification_preferences['order_updates']['email'])->toBeFalse();
    $this->endTenancy();

    $this->actingAsCustomer($customer, $tenant)
        ->from($this->tenantUrl($tenant, '/account/notifications'))
        ->put($this->tenantUrl($tenant, '/account/notifications'), ['preferences' => ['returns' => ['email' => '1', 'push' => '0']]])
        ->assertRedirect($this->tenantUrl($tenant, '/account/notifications'))
        ->assertSessionHas('status');

    $this->initializeTenancy($tenant);
    $saved = $customer->fresh()->notification_preferences;

    expect($saved['returns'])->toEqual(['email' => true, 'push' => false])
        ->and($saved['order_updates'])->toEqual(['email' => false, 'push' => false]);
});

it('rejects unknown notification types and channels', function (): void {
    $tenant = $this->createTenant('acme');
    $customer = $this->createCustomer($tenant);

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/notifications'), ['preferences' => ['hacks' => ['email' => true]]])
        ->assertUnprocessable()->assertJsonValidationErrors('preferences');

    $this->actingAsCustomer($customer, $tenant)
        ->putJson($this->tenantUrl($tenant, '/account/notifications'), ['preferences' => ['returns' => ['sms' => true]]])
        ->assertUnprocessable()->assertJsonValidationErrors('preferences.returns');
});
