<?php

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Engagement\Services\FavoritesService;
use App\Modules\Identity\Models\Customer;
use App\Shared\Money\Money;

/*
| Favorites (SF-ECO-033): toggle from a card (guests 401 + login URL), favorited ids,
| the favorites page service, isolation per customer.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('favs');
    [$this->product, $this->inactive, $this->customer, $this->other] = $this->tenant->run(fn (): array => [
        StoreProduct::factory()->create(['price' => Money::of('15.00')]),
        StoreProduct::factory()->create(['price' => Money::of('15.00'), 'is_active' => false]),
        Customer::factory()->create()->fresh(),
        Customer::factory()->create()->fresh(),
    ]);
    $this->url = fn (string $path): string => $this->tenantUrl($this->tenant, $path);
});

it('asks guests to sign in and returns no ids for them', function (): void {
    $this->postJson(($this->url)('/favorites/toggle'), ['store_product_id' => $this->product->id])
        ->assertUnauthorized()
        ->assertJsonPath('message', __st('favorites.sign_in_required'))
        ->assertJsonPath('redirect', fn (?string $url): bool => str_contains((string) $url, '/account/login'));

    $this->getJson(($this->url)('/favorites/ids'))->assertOk()->assertJsonPath('data.ids', [])->assertJsonPath('data.count', 0);
});

it('toggles favorites of the signed-in customer', function (): void {
    $this->actingAsCustomer($this->customer, $this->tenant);

    $this->postJson(($this->url)('/favorites/toggle'), [])->assertUnprocessable()->assertJsonValidationErrors('store_product_id');
    $this->postJson(($this->url)('/favorites/toggle'), ['store_product_id' => $this->inactive->id])->assertNotFound();

    $this->postJson(($this->url)('/favorites/toggle'), ['store_product_id' => $this->product->id])
        ->assertOk()->assertJsonPath('data', ['favorited' => true, 'count' => 1])->assertJsonPath('message', __st('favorites.added'));
    $this->getJson(($this->url)('/favorites/ids'))->assertJsonPath('data.ids', [$this->product->id]);

    $this->tenant->run(function (): void {
        $service = app(FavoritesService::class);
        expect($service->idsFor($this->customer))->toBe([$this->product->id])
            ->and($service->idsFor($this->other))->toBe([])
            ->and($service->idsFor(null))->toBe([])
            ->and($service->forCustomer($this->customer)->total())->toBe(1)
            ->and($service->forCustomer($this->customer)->items()[0]->store_product_id)->toBe($this->product->id);
    });

    $this->postJson(($this->url)('/favorites/toggle'), ['store_product_id' => $this->product->id])
        ->assertOk()->assertJsonPath('data', ['favorited' => false, 'count' => 0]);
});
