<?php

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\TenantStatus;
use Illuminate\Support\Facades\Auth;

require_once __DIR__.'/OwnerHelpers.php';

/*
| OWN-001 My stores (cards, summary, open store panel via SSO) and OWN-002 Select store.
*/

it('shows only the owner\'s own stores with their state and plan', function (): void {
    $owner = Owner::factory()->create();
    $live = ownerStore($owner, ['name' => 'Megz Store']);
    ownerSubscription($live, ['current_period_ends_at' => now()->addMonths(3)]);
    $trial = ownerStore($owner, ['name' => 'Lina Boutique']);
    ownerSubscription($trial, [])->update(['status' => SubscriptionStatus::Trial, 'trial_ends_at' => now()->addDays(5), 'current_period_ends_at' => null]);
    $suspended = ownerStore($owner, ['name' => 'Tech Hub', 'status' => TenantStatus::Suspended]);
    ownerInvoice($suspended, ['number' => 'INV-OWN-0412', 'status' => InvoiceStatus::Unpaid]);
    ownerStore(Owner::factory()->create(), ['name' => 'Someone Else Store']);

    $this->actingAs($owner, 'owner');

    $this->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Megz Store')
        ->assertSee('Lina Boutique')
        ->assertSee('Tech Hub')
        ->assertDontSee('Someone Else Store')
        ->assertSee(__('platform::enums.owner_store_state.suspended'))
        ->assertSee(__('platform::owner.dashboard.suspended_invoice', ['number' => 'INV-OWN-0412']))
        ->assertSee(route('owner.stores.open', $live->id), false)
        ->assertDontSee(route('owner.stores.open', $suspended->id), false);
});

it('shows the empty state for an owner without stores', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee(__('platform::owner.dashboard.empty_title'));
});

it('opens an owned store panel as its owner staff account (SSO)', function (): void {
    $owner = Owner::factory()->create(['email' => 'boss@example.test']);
    $acme = $this->createTenant('acme', ['owner_id' => $owner->id]);
    $this->createVendorOwner($acme, ['email' => 'boss@example.test']);
    $this->actingAs($owner, 'owner');

    $this->post(route('owner.stores.open', $acme->id))->assertRedirect($this->centralPanelUrl($acme, 'dashboard'));

    expect(Auth::guard('vendor')->check())->toBeTrue();
});

it('refuses to open another owner\'s store', function (): void {
    $owner = Owner::factory()->create();
    $other = ownerStore(Owner::factory()->create());
    $this->actingAs($owner, 'owner');

    $this->post(route('owner.stores.open', $other->id))->assertForbidden();
    expect(Auth::guard('vendor')->check())->toBeFalse();
});

it('reports a store that cannot be opened', function (): void {
    $owner = Owner::factory()->create();
    $store = ownerStore($owner, ['status' => TenantStatus::Suspended, 'name' => 'Closed Store']);
    $this->actingAs($owner, 'owner');

    $this->post(route('owner.stores.open', $store->id))
        ->assertRedirect(route('owner.dashboard'))
        ->assertSessionHas('error');
});

it('lists the stores to choose from after sign-in and disables closed ones', function (): void {
    $owner = Owner::factory()->create();
    $open = ownerStore($owner, ['name' => 'Open Store']);
    $closed = ownerStore($owner, ['name' => 'Closed Store', 'status' => TenantStatus::Suspended]);
    ownerStore(Owner::factory()->create(), ['name' => 'Foreign Store']);
    $this->actingAs($owner, 'owner');

    $response = $this->get(route('owner.select-store'))
        ->assertOk()
        ->assertSee('Open Store')
        ->assertSee('Closed Store')
        ->assertDontSee('Foreign Store')
        ->assertSee(route('owner.dashboard'), false);

    expect($response->getContent())->toMatch('/value="'.$closed->id.'"[^>]*disabled/')
        ->and($response->getContent())->not->toMatch('/value="'.$open->id.'"[^>]*disabled/');
});

it('sends an owner without stores from select store to add a store', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->get(route('owner.select-store'))->assertRedirect(route('owner.stores.create'));
});

it('validates and guards the store selection', function (): void {
    $owner = Owner::factory()->create();
    ownerStore($owner);
    $foreign = ownerStore(Owner::factory()->create());
    $this->actingAs($owner, 'owner');

    $this->post(route('owner.select-store.store'), [])->assertSessionHasErrors('tenant_id');
    $this->post(route('owner.select-store.store'), ['tenant_id' => $foreign->id])->assertSessionHasErrors('tenant_id');
});

it('opens the selected store panel', function (): void {
    $owner = Owner::factory()->create(['email' => 'boss@example.test']);
    $acme = $this->createTenant('acme', ['owner_id' => $owner->id]);
    $this->createVendorOwner($acme, ['email' => 'boss@example.test']);
    $this->actingAs($owner, 'owner');

    $this->post(route('owner.select-store.store'), ['tenant_id' => $acme->id])
        ->assertRedirect($this->centralPanelUrl($acme, 'dashboard'));
});

it('sends a signed-in owner from the central store picker to the owner picker', function (): void {
    $owner = Owner::factory()->create();
    ownerStore($owner);
    $this->actingAs($owner, 'owner');

    $this->get(route('central.tenant.stores'))->assertRedirect(route('owner.select-store'));
});
