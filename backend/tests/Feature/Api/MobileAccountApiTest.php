<?php

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Modules\Localization\Models\Country;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/MobileApiHelpers.php';

/*
| Mobile API v1 account (APP-019, APP-024 … APP-029): profile, password, notification
| switches, delete account, addresses (intl phone), wishlist, notifications inbox — always
| the signed-in customer's own rows (other customers' ids → 404).
*/

beforeEach(function (): void {
    $this->sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $this->tenant = $this->createTenant('shop', ['selling_country_ids' => [$this->sa->id], 'default_country_id' => $this->sa->id]);
    $this->customer = $this->createCustomer($this->tenant, ['email' => 'sara@example.test', 'name' => 'Sara']);
    $this->other = $this->createCustomer($this->tenant, ['email' => 'omar@example.test']);
    $this->headers = mobileHeaders($this->tenant);
    Sanctum::actingAs($this->customer, ['api'], 'sanctum');
});

it('updates the profile with an intl phone and asks to confirm a changed e-mail', function (): void {
    $this->tenant->run(fn () => $this->customer->forceFill(['email_verified_at' => now()])->save());

    $this->putJson(mobileApi('account/profile'), ['name' => 'Sara Ali', 'email' => 'sara.ali@example.test', 'phone' => '0501234567', 'phone_country' => 'SA', 'locale' => 'ar'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.customer.name', 'Sara Ali')
        ->assertJsonPath('data.customer.email_verified', false)
        ->assertJsonPath('data.customer.locale', 'ar');

    $this->putJson(mobileApi('account/profile'), ['name' => 'Sara', 'email' => 'omar@example.test'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('changes the password with the current one and signs other installs out', function (): void {
    $this->tenant->run(function (): void {
        $this->customer->createToken('mobile:other-install', ['api']);
    });

    $this->putJson(mobileApi('account/password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->putJson(mobileApi('account/password'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], $this->headers)
        ->assertOk();

    $this->tenant->run(function (): void {
        expect($this->customer->tokens()->count())->toBe(0)
            ->and(Hash::check('new-password-1', (string) $this->customer->fresh()?->password))->toBeTrue();
    });
});

it('reads and updates the notification switches partially', function (): void {
    $this->getJson(mobileApi('account/notification-preferences'), $this->headers)
        ->assertOk()
        ->assertJsonStructure(['data' => ['preferences' => ['order_updates' => ['email', 'push']]]]);

    $this->putJson(mobileApi('account/notification-preferences'), ['preferences' => ['promotions' => ['push' => false]]], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.preferences.promotions.push', false)
        ->assertJsonPath('data.preferences.order_updates.push', true);

    $this->putJson(mobileApi('account/notification-preferences'), ['preferences' => ['unknown' => ['push' => true]]], $this->headers)
        ->assertUnprocessable();
});

it('deletes the account after the password check', function (): void {
    $this->deleteJson(mobileApi('account'), ['password' => 'nope'], $this->headers)->assertUnprocessable();

    $this->deleteJson(mobileApi('account'), ['password' => 'password'], $this->headers)->assertOk();

    $this->tenant->run(function (): void {
        $row = Customer::withTrashed()->find($this->customer->id);
        expect($row?->trashed())->toBeTrue()->and($row?->email)->toBeNull();
    });
});

it('manages addresses with intl phones and hides other customers addresses', function (): void {
    $input = [
        'full_name' => 'Sara Ali', 'label' => 'Home', 'phone' => '050 123 4567', 'phone_country' => 'SA',
        'address_line_1' => 'King Fahd Rd, Building 12', 'city' => 'Riyadh', 'state' => 'Riyadh Province', 'country_id' => $this->sa->id,
    ];

    $id = $this->postJson(mobileApi('account/addresses'), $input, $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.address.full_name', 'Sara Ali')
        ->json('data.address.id');

    $this->postJson(mobileApi('account/addresses'), [...$input, 'phone' => '123'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    $this->getJson(mobileApi('account/addresses'), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.addresses')
        ->assertJsonPath('data.countries.0.iso2', 'SA');

    $this->putJson(mobileApi('account/addresses/'.$id), [...$input, 'label' => 'Work'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.address.label', 'Work');

    $foreign = $this->tenant->run(fn () => CustomerAddress::factory()->create(['customer_id' => $this->other->id, 'country_id' => $this->sa->id]));

    $this->putJson(mobileApi('account/addresses/'.$foreign->id), $input, $this->headers)->assertNotFound()->assertJsonPath('success', false);
    $this->deleteJson(mobileApi('account/addresses/'.$foreign->id), [], $this->headers)->assertNotFound();
    $this->postJson(mobileApi('account/addresses/'.$foreign->id.'/default'), [], $this->headers)->assertNotFound();

    $this->postJson(mobileApi('account/addresses/'.$id.'/default'), [], $this->headers)->assertOk();
    $this->deleteJson(mobileApi('account/addresses/'.$id), [], $this->headers)->assertOk()->assertJsonCount(0, 'data.addresses');
});

it('adds, lists and removes favorites', function (): void {
    $product = $this->tenant->run(fn () => StoreProduct::factory()->create(['is_active' => true]));

    $this->postJson(mobileApi('favorites'), ['store_product_id' => $product->id], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.count', 1);

    $this->postJson(mobileApi('favorites'), ['store_product_id' => $product->id], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.count', 1);

    $this->getJson(mobileApi('favorites'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.ids', [$product->id])
        ->assertJsonPath('data.meta.total', 1);

    $this->postJson(mobileApi('favorites'), ['store_product_id' => 999999], $this->headers)->assertNotFound();
    $this->postJson(mobileApi('favorites'), [], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('store_product_id');

    $this->deleteJson(mobileApi('favorites/'.$product->id), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.favorited', false);

    $this->tenant->run(fn () => expect(CustomerFavorite::query()->count())->toBe(0));
});

it('lists the inbox in the request language, marks rows read and hides other customers rows', function (): void {
    Mail::fake();

    [$mine, $foreign] = $this->tenant->run(function (): array {
        $this->customer->notify(new OrderCustomerNotification(null, 'confirmation', ['order_number' => '1001'], Str::uuid()->toString()));
        $this->other->notify(new OrderCustomerNotification(null, 'confirmation', ['order_number' => '1002'], Str::uuid()->toString()));

        return [$this->customer->notifications()->sole(), $this->other->notifications()->sole()];
    });

    $this->getJson(mobileApi('notifications'), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $mine->id)
        ->assertJsonPath('data.items.0.read', false)
        ->assertJsonPath('data.items.0.title', 'Order 1001 confirmed')
        ->assertJsonPath('data.items.0.type', 'order_updates')
        ->assertJsonPath('data.unread_count', 1);

    $this->postJson(mobileApi('notifications/'.$foreign->id.'/read'), [], $this->headers)->assertNotFound();
    $this->deleteJson(mobileApi('notifications/'.$foreign->id), [], $this->headers)->assertNotFound();

    $this->postJson(mobileApi('notifications/'.$mine->id.'/read'), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.notification.read', true)
        ->assertJsonPath('data.unread_count', 0);

    $this->getJson(mobileApi('notifications?unread=1'), $this->headers)->assertOk()->assertJsonCount(0, 'data.items');
    $this->postJson(mobileApi('notifications/read-all'), [], $this->headers)->assertOk();
    $this->getJson(mobileApi('notifications/unread-count'), $this->headers)->assertOk()->assertJsonPath('data.unread_count', 0);
    $this->deleteJson(mobileApi('notifications/'.$mine->id), [], $this->headers)->assertOk();
});
