<?php

use App\Modules\Commerce\Enums\OrderKind;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use Illuminate\Support\Str;

/*
| VEN-025 / 025a / 025b / 025c: vendor Customers — list with KPIs and order figures,
| CSV report, create / edit (phone E.164, newsletter), detail (summary, orders &
| payments, profile), activate / deactivate, delete (anonymised), the address modal
| (cities by country), permissions and store isolation.
*/

beforeEach(function (): void {
    $this->sa = Country::factory()->iso('SA')->create();
    $this->eg = Country::factory()->iso('EG')->create();
    $this->tenant = $this->createTenant('custshop', ['selling_country_ids' => [$this->sa->id]]);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/sales/customers'.$path);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->data = fn (string $path = '', string $query = ''): array => $this->getJson(($this->url)($path.'/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
});

function customerOrder(Customer $customer, OrderStatus $status, int $total, ?string $placedAt = null): Order
{
    return Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'number' => 'C'.Str::upper(Str::random(9)),
        'checkout_group' => (string) Str::uuid(),
        'kind' => OrderKind::Own,
        'status' => $status,
        'payment_status' => $status === OrderStatus::PendingPayment ? PaymentStatus::Pending : PaymentStatus::Paid,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'currency' => 'USD',
        'items_grand_total' => $total,
        'grand_total' => $total,
        'totals' => [],
        'payment_gateway' => 'cod',
        'placed_at' => $placedAt ?? now()->subDays(2),
    ]);
}

it('sends guests to the login and forbids staff without customer permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    $viewer = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'customers.view']);

        return $staff;
    });
    $customer = $this->createCustomer($this->tenant);
    $this->actingAsVendor($viewer, $this->tenant);

    $this->get(($this->url)())->assertOk()->assertSee(__('identity::customers.subtitle'))->assertDontSee(__('identity::customers.add'))->assertDontSee(__('identity::customers.report'));
    $this->get(($this->url)('/'.$customer->id))->assertOk()->assertDontSee(__('identity::customers.deactivate'));
    $this->get(($this->url)('/export'))->assertForbidden();
    $this->getJson(($this->url)('/create'))->assertForbidden();
    $this->postJson(($this->url)(), ['first_name' => 'X'])->assertForbidden();
    $this->getJson(($this->url)('/'.$customer->id.'/edit'))->assertForbidden();
    $this->putJson(($this->url)('/'.$customer->id), [])->assertForbidden();
    $this->patchJson(($this->url)('/'.$customer->id.'/active'))->assertForbidden();
    $this->deleteJson(($this->url)('/'.$customer->id))->assertForbidden();
    $this->getJson(($this->url)('/'.$customer->id.'/addresses/create'))->assertForbidden();
    $this->getJson(($this->url)('/cities?country_id='.$this->sa->id))->assertForbidden();

    $none = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view']);

        return $staff;
    });
    $this->actingAsVendor($none, $this->tenant);

    foreach (['', '/data', '/'.$customer->id, '/'.$customer->id.'/orders'] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }
});

it('lists customers with KPIs, order figures, filters and the CSV report', function (): void {
    [$buyer, $idle] = $this->tenant->run(function (): array {
        $buyer = Customer::factory()->create(['name' => 'Ali Mohamed', 'phone' => '+966501234567', 'phone_country' => 'SA']);
        $idle = Customer::factory()->create(['name' => 'Ziad Hassan', 'status' => AccountStatus::Suspended]);
        customerOrder($buyer, OrderStatus::Delivered, 62000, now()->subDays(3)->toDateTimeString());
        customerOrder($buyer, OrderStatus::Processing, 10000, now()->subDay()->toDateTimeString());
        customerOrder($buyer, OrderStatus::Cancelled, 5000);
        customerOrder($buyer, OrderStatus::PendingPayment, 9900);

        return [$buyer, $idle];
    });
    ($this->as)();

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('identity::customers.kpi.buyers'))
        ->assertSee('$720.00')
        ->assertSee(__('identity::customers.add'));

    $rows = collect(($this->data)()['data'])->keyBy(fn (array $row): string => strip_tags($row['id']));
    expect($rows)->toHaveCount(2)
        ->and($rows['#'.$buyer->id]['orders'])->toBe('3')
        ->and(strip_tags($rows['#'.$buyer->id]['lifetime']))->toBe('$720.00')
        ->and($rows['#'.$buyer->id]['customer'])->toContain('Ali Mohamed')
        ->and($rows['#'.$idle->id]['orders'])->toBe('0');

    expect(($this->data)('', '&segment=buyers')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('', '&segment=no_orders')['data'][0]['customer'])->toContain('Ziad Hassan')
        ->and(($this->data)('', '&status=suspended')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('', '&search=501234567')['recordsFiltered'])->toBe(1)
        ->and(($this->data)('', '&search=%23'.$idle->id)['data'][0]['customer'])->toContain('Ziad Hassan');

    $csv = $this->get(($this->url)('/export?segment=buyers'))->assertOk()->streamedContent();
    expect($csv)->toContain('lifetime_value_usd')->toContain('Ali Mohamed')->toContain('720.00')->not->toContain('Ziad Hassan');
});

it('creates and edits a customer with an E.164 phone and the newsletter', function (): void {
    ($this->as)();

    $this->get(($this->url)('/create'), $this->ajax)->assertOk()->assertSee('vendor-customer-form', false)->assertSee('data-phone', false);

    $this->postJson(($this->url)(), ['first_name' => 'Noor'])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    $this->postJson(($this->url)(), ['first_name' => 'Noor', 'phone' => '123', 'phone_country' => 'SA'])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    $this->postJson(($this->url)(), ['first_name' => 'Noor', 'phone' => '050 123 4567', 'phone_country' => 'sa', 'newsletter' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->postJson(($this->url)(), [
        'first_name' => 'Noor', 'last_name' => 'Hassan', 'email' => 'Noor@iCloud.com',
        'phone' => '050 123 4567', 'phone_country' => 'sa', 'locale' => 'ar', 'newsletter' => '1',
    ])->assertCreated();

    [$customer, $subscriber] = $this->tenant->run(fn (): array => [Customer::query()->where('email', 'noor@icloud.com')->firstOrFail(), NewsletterSubscriber::query()->first()]);
    expect($customer->name)->toBe('Noor Hassan')
        ->and($customer->phone)->toBe('+966501234567')
        ->and($customer->phone_country)->toBe('SA')
        ->and($customer->password)->toBeNull()
        ->and($customer->status)->toBe(AccountStatus::Active)
        ->and($subscriber?->email)->toBe('noor@icloud.com')
        ->and($subscriber?->customer_id)->toBe($customer->id);

    $this->postJson(($this->url)(), ['first_name' => 'Dup', 'email' => 'noor@icloud.com', 'phone' => '0501234567', 'phone_country' => 'SA'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);

    $this->get(($this->url)('/'.$customer->id.'/edit'), $this->ajax)->assertOk()->assertSee('value="Hassan"', false);
    $this->putJson(($this->url)('/'.$customer->id), ['first_name' => 'Noor', 'last_name' => 'Saleh', 'email' => 'noor@icloud.com', 'phone' => '', 'newsletter' => '0'])->assertOk();

    $this->tenant->run(function () use ($customer): void {
        $fresh = $customer->fresh();
        expect($fresh?->name)->toBe('Noor Saleh')
            ->and($fresh?->phone)->toBeNull()
            ->and(NewsletterSubscriber::query()->first()?->unsubscribed_at)->not->toBeNull();
    });
});

it('shows the detail with order figures, toggles active and deletes the customer', function (): void {
    $customer = $this->tenant->run(function (): Customer {
        $customer = Customer::factory()->create(['name' => 'Sara Khalid', 'email' => 'sara.k@example.com', 'locale' => 'ar']);
        customerOrder($customer, OrderStatus::Processing, 7620, '2026-11-24 10:00:00');
        customerOrder($customer, OrderStatus::Delivered, 8400, '2026-10-02 10:00:00');
        CustomerAddress::factory()->create(['customer_id' => $customer->id, 'country_id' => $this->sa->id, 'label' => 'Home', 'address_line_1' => 'King Saud St 14', 'city' => 'Dammam', 'is_default' => true]);
        NewsletterSubscriber::query()->create(['email' => 'sara.k@example.com', 'customer_id' => $customer->id, 'subscribed_at' => now()]);

        return $customer;
    });
    ($this->as)();

    $this->get(($this->url)('/'.$customer->id))->assertOk()
        ->assertSee('Sara Khalid')
        ->assertSee('$160.20')
        ->assertSee('24 Nov')
        ->assertSee('King Saud St 14')
        ->assertSee(__('identity::customers.detail.subscribed'))
        ->assertSee(__('identity::customers.deactivate'));

    $orders = $this->getJson(($this->url)('/'.$customer->id.'/orders?draw=1&start=0&length=10'))->assertOk()->json();
    expect($orders['recordsTotal'])->toBe(2)->and($orders['data'][0]['order'])->toContain('/admin/sales/orders/');

    $this->patchJson(($this->url)('/'.$customer->id.'/active?from_detail=1'))->assertOk()->assertJsonPath('redirect', ($this->url)('/'.$customer->id));
    expect($this->tenant->run(fn () => $customer->fresh()?->status))->toBe(AccountStatus::Suspended);
    $this->get(($this->url)('/'.$customer->id))->assertSee(__('identity::customers.activate'));
    // QA-P8: toggling from the list stays on the list (no redirect to the detail page).
    $this->patchJson(($this->url)('/'.$customer->id.'/active'))->assertOk()->assertJsonMissingPath('redirect');
    expect($this->tenant->run(fn () => $customer->fresh()?->status))->toBe(AccountStatus::Active);

    $this->deleteJson(($this->url)('/'.$customer->id.'?from_detail=1'))->assertOk()->assertJsonPath('redirect', ($this->url)());

    $this->tenant->run(function () use ($customer): void {
        $trashed = Customer::withTrashed()->findOrFail($customer->id);
        expect($trashed->trashed())->toBeTrue()
            ->and($trashed->email)->toBeNull()
            ->and(CustomerAddress::query()->where('customer_id', $customer->id)->count())->toBe(0)
            ->and(NewsletterSubscriber::query()->first()?->unsubscribed_at)->not->toBeNull()
            ->and(Order::query()->where('customer_id', $customer->id)->count())->toBe(2);
    });
    $this->get(($this->url)('/'.$customer->id))->assertNotFound();
});

it('manages addresses in the modal with cities of the selected country', function (): void {
    $customer = $this->createCustomer($this->tenant, ['name' => 'Sara Khalid', 'phone' => '+966501234567', 'phone_country' => 'SA']);
    City::factory()->for($this->sa)->named(['en' => 'Dammam', 'ar' => 'الدمام'])->create();
    City::factory()->for($this->sa)->named(['en' => 'Riyadh', 'ar' => 'الرياض'])->create();
    City::factory()->for($this->eg)->named(['en' => 'Cairo', 'ar' => 'القاهرة'])->create();
    ($this->as)();

    $cities = $this->getJson(($this->url)('/cities?country_id='.$this->sa->id.'&q=Dam'))->assertOk()->json('results');
    expect($cities)->toBe([['id' => 'Dammam', 'text' => 'Dammam']]);
    $this->getJson(($this->url)('/cities?country_id='.$this->eg->id))->assertNotFound();
    $this->getJson(($this->url)('/cities'))->assertUnprocessable();

    $this->get(($this->url)('/'.$customer->id.'/addresses/create'), $this->ajax)->assertOk()->assertSee('vendor-address-form', false)->assertSee('data-city-select', false);

    $payload = ['full_name' => 'Sara Khalid', 'phone' => '0501234567', 'phone_country' => 'SA', 'country_id' => $this->sa->id, 'city' => 'Dammam', 'address_line_1' => 'King Saud St 14', 'label' => 'Home'];
    $this->postJson(($this->url)('/'.$customer->id.'/addresses'), [...$payload, 'country_id' => $this->eg->id])->assertUnprocessable()->assertJsonValidationErrors(['country_id']);
    $this->postJson(($this->url)('/'.$customer->id.'/addresses'), $payload)->assertCreated();
    $this->postJson(($this->url)('/'.$customer->id.'/addresses'), [...$payload, 'label' => 'Work', 'city' => 'Riyadh'])->assertCreated();

    [$home, $work] = $this->tenant->run(fn () => CustomerAddress::query()->orderBy('id')->get()->all());
    expect($home->is_default)->toBeTrue()->and($work->is_default)->toBeFalse()->and($home->phone)->toBe('+966501234567');

    $this->get(($this->url)('/'.$customer->id.'/addresses/'.$work->id.'/edit'), $this->ajax)->assertOk()->assertSee('Riyadh');
    $this->putJson(($this->url)('/'.$customer->id.'/addresses/'.$work->id), [...$payload, 'label' => 'Work', 'address_line_1' => 'Olaya St 2'])->assertOk();
    $this->postJson(($this->url)('/'.$customer->id.'/addresses/'.$work->id.'/default'))->assertOk();
    expect($this->tenant->run(fn () => [$home->fresh()?->is_default, $work->fresh()?->is_default, $work->fresh()?->address_line_1]))->toBe([false, true, 'Olaya St 2']);

    $this->deleteJson(($this->url)('/'.$customer->id.'/addresses/'.$work->id))->assertOk();
    expect($this->tenant->run(fn () => $home->fresh()?->is_default))->toBeTrue();

    $other = $this->createCustomer($this->tenant);
    $this->putJson(($this->url)('/'.$other->id.'/addresses/'.$home->id), $payload)->assertNotFound();
});

it('keeps customers of other stores out of reach', function (): void {
    $otherTenant = $this->createTenant('othercust');
    $stranger = $this->createCustomer($otherTenant, ['name' => 'Other Store Customer']);
    $mine = $this->createCustomer($this->tenant, ['name' => 'Mine Customer']);
    ($this->as)();

    $names = collect(($this->data)()['data'])->pluck('customer')->implode(' ');
    expect($names)->toContain('Mine Customer')->not->toContain('Other Store Customer');

    if ($stranger->id !== $mine->id) {
        $this->get(($this->url)('/'.$stranger->id))->assertNotFound();
        $this->deleteJson(($this->url)('/'.$stranger->id))->assertNotFound();
    }

    $this->tenant->run(fn () => expect(Customer::query()->where('name', 'Other Store Customer')->exists())->toBeFalse());
});
