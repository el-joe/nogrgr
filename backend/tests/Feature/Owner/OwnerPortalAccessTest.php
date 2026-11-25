<?php

use App\Modules\Identity\Models\Owner;

/*
| Owner portal: every page needs the owner session (guests go to the central store
| login) and an active owner (suspended → 403).
*/

dataset('owner pages', [
    'dashboard' => ['owner.dashboard', []],
    'select store' => ['owner.select-store', []],
    'subscriptions' => ['owner.subscriptions.index', []],
    'subscriptions data' => ['owner.subscriptions.data', []],
    'domains' => ['owner.domains.index', []],
    'domains data' => ['owner.domains.data', []],
    'domain' => ['owner.domains.show', ['domain' => 1]],
    'invoices' => ['owner.invoices.index', []],
    'invoices data' => ['owner.invoices.data', []],
    'invoice' => ['owner.invoices.show', ['invoice' => 1]],
    'invoice print' => ['owner.invoices.print', ['invoice' => 1]],
    'profile' => ['owner.profile.edit', []],
]);

it('sends guests to the owner login', function (string $route, array $parameters): void {
    $this->get(route($route, $parameters))->assertRedirect(route('central.tenant.login'));
})->with('owner pages');

it('refuses a suspended owner', function (string $route, array $parameters): void {
    $this->actingAs(Owner::factory()->suspended()->create(), 'owner');

    // Bound records that do not exist answer 404 before the gate; nothing is shown either way.
    expect($this->get(route($route, $parameters))->status())->toBeIn([403, 404]);
})->with('owner pages');

it('sends guests posting to the owner login', function (): void {
    $this->post(route('owner.domains.store'), [])->assertRedirect(route('central.tenant.login'));
    $this->put(route('owner.profile.update'), [])->assertRedirect(route('central.tenant.login'));
    $this->post(route('owner.subscriptions.renew', 1), [])->assertRedirect(route('central.tenant.login'));
    $this->post(route('owner.stores.open', 1))->assertRedirect(route('central.tenant.login'));
});

it('lists every portal page in the owner sidebar', function (): void {
    $this->actingAs(Owner::factory()->create(), 'owner');

    $this->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee(route('owner.subscriptions.index'), false)
        ->assertSee(route('owner.domains.index'), false)
        ->assertSee(route('owner.invoices.index'), false)
        ->assertSee(route('owner.profile.edit'), false)
        ->assertSee(route('owner.stores.create'), false);
});
