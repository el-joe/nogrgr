<?php

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Services\CustomerAddressService;
use App\Modules\Identity\Services\CustomerCountryResolver;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;

/*
| Saved addresses (spec §1, §2, §4): JSON endpoints under /account/addresses used by the
| account page and checkout, the canonical SaveCustomerAddressRequest and the default rules.
*/

/**
 * @return array{0: Tenant, 1: Customer, 2: Country}
 */
function addressBook(): array
{
    $saudi = Country::factory()->iso('SA')->create();
    Country::factory()->iso('AE')->create();
    $tenant = test()->createTenant('acme');
    $customer = test()->createCustomer($tenant, ['email' => 'sara@example.test']);

    return [$tenant, $customer, $saudi];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressInput(Country $country, array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Sara Ali',
        'label' => 'Home',
        'phone' => '050 123 4567',
        'phone_country' => 'SA',
        'email' => '',
        'address_line_1' => 'King Fahd Rd, Building 12',
        'city' => 'Riyadh',
        'state' => 'Riyadh Province',
        'country_id' => $country->id,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function savedAddress(Tenant $tenant, Customer $customer, array $attributes = []): CustomerAddress
{
    test()->initializeTenancy($tenant);
    $address = CustomerAddress::factory()->for($customer)->create($attributes)->fresh();
    test()->endTenancy();

    return $address;
}

it('requires a signed-in customer for every address endpoint', function (string $method, string $path): void {
    $tenant = $this->createTenant('acme');

    $this->{$method.'Json'}($this->tenantUrl($tenant, $path))->assertUnauthorized();
})->with([
    'page' => ['get', '/account/addresses'],
    'list' => ['get', '/account/addresses/list'],
    'store' => ['post', '/account/addresses'],
    'update' => ['put', '/account/addresses/1'],
    'destroy' => ['delete', '/account/addresses/1'],
    'default' => ['post', '/account/addresses/1/default'],
]);

it('renders the address book with the store countries and endpoints', function (): void {
    [$tenant, $customer] = addressBook();

    $this->actingAsCustomer($customer, $tenant)
        ->get($this->tenantUrl($tenant, '/account/addresses'))
        ->assertOk()
        ->assertSee(__('identity::addresses.empty'))
        ->assertSee('id="address-book"', false)
        ->assertSee('Saudi Arabia');
});

it('stores the first address as default with an E.164 phone', function (): void {
    [$tenant, $customer, $saudi] = addressBook();

    $response = $this->actingAsCustomer($customer, $tenant)
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($saudi, ['latitude' => 24.7136, 'longitude' => 46.6753]))
        ->assertCreated()
        ->assertJson(['success' => true, 'message' => __('identity::addresses.saved')])
        ->assertJsonPath('data.address.phone', '+966501234567')
        ->assertJsonPath('data.address.phone_country', 'SA')
        ->assertJsonPath('data.address.is_default', true)
        ->assertJsonPath('data.address.email', null)
        ->assertJsonPath('data.address.country.iso2', 'SA')
        ->assertJsonPath('data.address.latitude', 24.7136)
        ->assertJsonCount(1, 'data.addresses');

    expect($response->json('data.address.one_line'))->toBe('King Fahd Rd, Building 12, Riyadh, Riyadh Province, Saudi Arabia');
});

it('validates every rule of the canonical address form', function (array $overrides, string $field): void {
    [$tenant, $customer, $saudi] = addressBook();

    $this->actingAsCustomer($customer, $tenant)
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($saudi, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'full_name required' => [['full_name' => ''], 'full_name'],
    'full_name max 120' => [['full_name' => str_repeat('a', 121)], 'full_name'],
    'label max 50' => [['label' => str_repeat('a', 51)], 'label'],
    'phone required' => [['phone' => ''], 'phone'],
    'phone not a number' => [['phone' => 'call me'], 'phone'],
    'phone too short for SA' => [['phone' => '05012'], 'phone'],
    'phone of another country' => [['phone' => '+201001234567'], 'phone'],
    'phone_country required' => [['phone_country' => ''], 'phone_country'],
    'phone_country unknown' => [['phone_country' => 'ZZ'], 'phone_country'],
    'email format' => [['email' => 'nope'], 'email'],
    'email max 120' => [['email' => str_repeat('a', 112).'@test.com'], 'email'],
    'street required' => [['address_line_1' => ''], 'address_line_1'],
    'street max 300' => [['address_line_1' => str_repeat('a', 301)], 'address_line_1'],
    'city required' => [['city' => ''], 'city'],
    'city max 100' => [['city' => str_repeat('a', 101)], 'city'],
    'state max 100' => [['state' => str_repeat('a', 101)], 'state'],
    'country required' => [['country_id' => ''], 'country_id'],
    'country unknown' => [['country_id' => 999999], 'country_id'],
    'latitude range' => [['latitude' => 91, 'longitude' => 10], 'latitude'],
    'longitude with latitude' => [['latitude' => 24.7], 'longitude'],
    'is_default boolean' => [['is_default' => 'maybe'], 'is_default'],
]);

it('refuses inactive countries and countries the store does not sell to', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    $inactive = Country::factory()->iso('KW')->inactive()->create();
    $egypt = Country::factory()->iso('EG')->create();
    $tenant->update(['selling_country_ids' => [$saudi->id]]);

    $this->actingAsCustomer($customer, $tenant)
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($inactive, ['phone' => '+96550012345', 'phone_country' => 'KW']))
        ->assertJsonValidationErrors('country_id');

    $this->actingAsCustomer($customer, $tenant)
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($egypt, ['phone' => '01001234567', 'phone_country' => 'EG']))
        ->assertJsonValidationErrors('country_id');
});

it('validates the phone for the selected country', function (string $country, string $phone, bool $valid): void {
    [$tenant, $customer, $saudi] = addressBook();

    $response = $this->actingAsCustomer($customer, $tenant)
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($saudi, ['phone' => $phone, 'phone_country' => $country]));

    $valid ? $response->assertCreated() : $response->assertJsonValidationErrors('phone');
})->with([
    'SA national' => ['SA', '0501234567', true],
    'SA international' => ['SA', '+966 50 123 4567', true],
    'AE mobile' => ['AE', '050 123 4567', true],
    'EG mobile' => ['EG', '010 0123 4567', true],
    'US number' => ['US', '(201) 555-0123', true],
    'GB mobile' => ['GB', '07400 123456', true],
    'SA too long' => ['SA', '05012345678901', false],
    'AE number with SA country' => ['SA', '+971501234567', false],
    'EG landline length wrong' => ['EG', '0123', false],
]);

it('answers Arabic validation messages with Arabic field names', function (): void {
    [$tenant, $customer, $saudi] = addressBook();

    $response = $this->actingAsCustomer($customer, $tenant)
        ->withCredentials()
        ->withUnencryptedCookie('locale', 'ar')
        ->postJson($this->tenantUrl($tenant, '/account/addresses'), addressInput($saudi, ['city' => '']))
        ->assertUnprocessable();

    expect($response->json('errors.city.0'))->toBe('حقل المدينة مطلوب.');
});

it('keeps exactly one default and promotes the oldest on delete', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    $url = fn (string $path = ''): string => $this->tenantUrl($tenant, '/account/addresses'.$path);

    $first = $this->actingAsCustomer($customer, $tenant)->postJson($url(), addressInput($saudi, ['label' => 'Home']))->json('data.address.id');
    $second = $this->actingAsCustomer($customer, $tenant)->postJson($url(), addressInput($saudi, ['label' => 'Work']))->assertJsonPath('data.address.is_default', false)->json('data.address.id');
    $third = $this->actingAsCustomer($customer, $tenant)->postJson($url(), addressInput($saudi, ['label' => 'Gym', 'is_default' => true]))->assertJsonPath('data.address.is_default', true)->json('data.address.id');

    $defaults = fn (): array => collect($this->actingAsCustomer($customer, $tenant)->getJson($url('/list'))->assertOk()->json('data'))
        ->where('is_default', true)->pluck('id')->all();

    expect($defaults())->toBe([$third]);

    $this->actingAsCustomer($customer, $tenant)->postJson($url('/'.$second.'/default'))->assertOk()->assertJsonPath('data.address.is_default', true);
    expect($defaults())->toBe([$second]);

    $this->actingAsCustomer($customer, $tenant)->putJson($url('/'.$second), addressInput($saudi, ['label' => 'Office', 'is_default' => false]))
        ->assertOk()->assertJsonPath('data.address.is_default', true)->assertJsonPath('data.address.label', 'Office');

    $this->actingAsCustomer($customer, $tenant)->deleteJson($url('/'.$second))->assertOk()->assertJsonCount(2, 'data.addresses');
    expect($defaults())->toBe([$first]);

    $this->actingAsCustomer($customer, $tenant)->deleteJson($url('/'.$third))->assertOk();
    $this->actingAsCustomer($customer, $tenant)->deleteJson($url('/'.$first))->assertOk()->assertJsonCount(0, 'data.addresses');
});

it('lists the default address first', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    savedAddress($tenant, $customer, ['country_id' => $saudi->id, 'label' => 'Old']);
    $default = savedAddress($tenant, $customer, ['country_id' => $saudi->id, 'label' => 'Main', 'is_default' => true]);

    $this->actingAsCustomer($customer, $tenant)
        ->getJson($this->tenantUrl($tenant, '/account/addresses/list'))
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.0.id', $default->id);
});

it('works as a plain HTML form with redirect and flash', function (): void {
    [$tenant, $customer, $saudi] = addressBook();

    $this->actingAsCustomer($customer, $tenant)
        ->from($this->tenantUrl($tenant, '/account/addresses'))
        ->post($this->tenantUrl($tenant, '/account/addresses'), addressInput($saudi))
        ->assertRedirect($this->tenantUrl($tenant, '/account/addresses'))
        ->assertSessionHas('status', __('identity::addresses.saved'));
});

it('answers 404 for another customer\'s address', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    $other = $this->createCustomer($tenant);
    $foreign = savedAddress($tenant, $other, ['country_id' => $saudi->id, 'is_default' => true]);

    $this->actingAsCustomer($customer, $tenant)->putJson($this->tenantUrl($tenant, '/account/addresses/'.$foreign->id), addressInput($saudi))->assertNotFound();
    $this->actingAsCustomer($customer, $tenant)->deleteJson($this->tenantUrl($tenant, '/account/addresses/'.$foreign->id))->assertNotFound();
    $this->actingAsCustomer($customer, $tenant)->postJson($this->tenantUrl($tenant, '/account/addresses/'.$foreign->id.'/default'))->assertNotFound();
    $this->actingAsCustomer($customer, $tenant)->getJson($this->tenantUrl($tenant, '/account/addresses/list'))->assertJsonCount(0, 'data');

    $this->initializeTenancy($tenant);
    expect(CustomerAddress::query()->whereKey($foreign->id)->value('is_default'))->toBeTrue();
});

it('isolates stores: a customer of store A never sees store B\'s addresses', function (): void {
    [$acme, $customer, $saudi] = addressBook();
    $beta = $this->createTenant('beta');
    $betaCustomer = $this->createCustomer($beta);
    $betaAddress = savedAddress($beta, $betaCustomer, ['country_id' => $saudi->id, 'label' => 'Beta home']);

    expect($betaCustomer->id)->toBe($customer->id);

    $this->actingAsCustomer($customer, $acme)
        ->getJson($this->tenantUrl($beta, '/account/addresses/list'))
        ->assertUnauthorized();

    $this->actingAsCustomer($customer, $acme)
        ->deleteJson($this->tenantUrl($beta, '/account/addresses/'.$betaAddress->id))
        ->assertUnauthorized();

    $this->initializeTenancy($beta);
    expect(CustomerAddress::query()->count())->toBe(1);
});

it('selects checkout addresses and snapshots them for the order', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    $first = savedAddress($tenant, $customer, ['country_id' => $saudi->id, 'label' => 'Home', 'email' => null]);
    $default = savedAddress($tenant, $customer, ['country_id' => $saudi->id, 'label' => 'Work', 'is_default' => true, 'email' => 'work@example.test']);

    $this->initializeTenancy($tenant);
    $service = app(CustomerAddressService::class);

    $selection = $service->forCheckout($customer);
    expect($selection->shipping?->id)->toBe($default->id)
        ->and($selection->billingSameAsShipping)->toBeTrue()
        ->and($selection->billingSnapshot()?->addressId)->toBe($default->id);

    $split = $service->forCheckout($customer, $default->id, $first->id, billingSameAsShipping: false);
    $billing = $split->billingSnapshot();

    expect($split->billing?->id)->toBe($first->id)
        ->and($billing?->email)->toBe('sara@example.test')
        ->and($billing?->countryIso2)->toBe('SA')
        ->and($billing?->countryName)->toMatchArray(['en' => 'Saudi Arabia', 'ar' => 'المملكة العربية السعودية'])
        ->and($split->shippingSnapshot()?->email)->toBe('work@example.test')
        ->and($billing?->oneLine('ar'))->toEndWith('المملكة العربية السعودية');

    $foreign = $service->forCheckout($customer, 999999);
    expect($foreign->shipping?->id)->toBe($default->id);

    $empty = $service->forCheckout($this->createCustomer($tenant));
    expect($empty->hasAddress())->toBeFalse()->and($empty->shippingSnapshot())->toBeNull();
});

it('resolves the pricing country: selected address, then default, then session', function (): void {
    [$tenant, $customer, $saudi] = addressBook();
    $emirates = Country::query()->where('iso2', 'AE')->sole();
    $selected = savedAddress($tenant, $customer, ['country_id' => $emirates->id]);
    savedAddress($tenant, $customer, ['country_id' => $saudi->id, 'is_default' => true]);

    $this->initializeTenancy($tenant);
    $this->actingAs($customer, 'customer');
    session([CustomerCountryResolver::SESSION_KEY => 123]);
    $resolver = app(CustomerCountryResolver::class);

    expect($resolver->resolveId($selected->id))->toBe($emirates->id)
        ->and($resolver->resolveId(999999))->toBe($saudi->id)
        ->and($resolver->resolveId(null, 77))->toBe(77)
        ->and($resolver->resolveIso2())->toBe('SA');

    auth('customer')->logout();
    expect(app(CustomerCountryResolver::class)->resolveId())->toBe(123);
});
