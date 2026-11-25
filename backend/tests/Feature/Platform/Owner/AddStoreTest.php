<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require_once __DIR__.'/../Registration/SignUpHelpers.php';
require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| OWN-007 Add another store: a signed-in owner enters store details, then continues in
| the standard plan / payment steps with the e-mail already proven.
*/

beforeEach(function (): void {
    Notification::fake();
});

it('requires the owner session', function (): void {
    $this->get(route('owner.stores.create'))->assertRedirect(route('central.tenant.login'));
    $this->post(route('owner.stores.store'), [])->assertRedirect(route('central.tenant.login'));
});

it('refuses a suspended owner', function (): void {
    $this->actingAs(Owner::factory()->suspended()->create(), 'owner');

    $this->get(route('owner.stores.create'))->assertForbidden();
});

it('renders the store details step in the owner portal', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->get(route('owner.stores.create'))
        ->assertOk()
        ->assertSee(__('platform::signup.add_store.title'))
        ->assertSee('name="slug"', false)
        ->assertSee('name="default_country_id"', false)
        ->assertSee(route('owner.stores.create'), false);
});

it('checks the store address live', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');
    vendorsTenant(['slug' => 'taken-store']);

    $this->getJson(route('owner.stores.availability', ['slug' => 'taken-store']))->assertOk()->assertJson(['available' => false]);
    $this->getJson(route('owner.stores.availability', ['slug' => 'free-store-'.Str::lower(Str::random(4))]))->assertOk()->assertJson(['available' => true]);
});

it('validates the store details', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');
    vendorsTenant(['slug' => 'taken-store']);

    $this->post(route('owner.stores.store'), ['store_name' => '', 'slug' => 'taken-store'])->assertSessionHasErrors(['store_name', 'slug']);
});

it('continues into the plan step and creates a pre-filled registration for the same owner', function (): void {
    $owner = Owner::factory()->create(['name' => 'Mohamed Saleh', 'email' => 'owner@example.com', 'phone' => '+966501234567']);
    $this->actingAs($owner, 'owner');
    $slug = 'kids-'.Str::lower(Str::random(5));

    $this->post(route('owner.stores.store'), ['store_name' => 'Megz Kids', 'slug' => Str::upper($slug)])
        ->assertRedirect(route('central.register.plan'));

    $plan = paidPlan(14);
    $this->get(route('central.register.plan'))->assertOk()->assertSee($plan->name);

    $this->post(route('central.register.plan.store'), ['plan_id' => $plan->id, 'term' => 'yearly', 'choice' => 'trial'])
        ->assertRedirect(route('central.register.continue'));

    $registration = VendorRegistration::query()->sole();
    expect($registration->owner_id)->toBe($owner->id)
        ->and($registration->email)->toBe('owner@example.com')
        ->and($registration->owner_name)->toBe('Mohamed Saleh')
        ->and($registration->store_name)->toBe('Megz Kids')
        ->and($registration->slug)->toBe($slug)
        ->and($registration->status)->toBe(RegistrationStatus::Incomplete)
        ->and($registration->getRawOriginal('password'))->toBe($owner->getRawOriginal('password'));

    // The account step is already done: the completion link opens the store step.
    $link = (string) $this->get(route('central.register.continue'))->headers->get('Location');
    $this->get($link)->assertOk()->assertSee('data-store-form', false)->assertSee('Megz Kids');
    Notification::assertNothingSent();
});

it('reserves the address so nobody else can take it meanwhile', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');
    $this->post(route('owner.stores.store'), ['store_name' => 'Shop', 'slug' => 'reserved-shop']);
    $this->post(route('central.register.plan.store'), ['plan_id' => Plan::factory()->withTrial()->create()->id, 'term' => 'monthly', 'choice' => 'trial']);

    $this->getJson(route('owner.stores.availability', ['slug' => 'reserved-shop']))->assertJson(['available' => false]);
    expect(Tenant::query()->where('slug', 'reserved-shop')->exists())->toBeFalse();
});
