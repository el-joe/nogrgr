<?php

use App\Modules\Engagement\Models\CustomerFavorite;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Account › Wishlist (SF-ECO-033): the customer's favorites as cards, search, empty state;
| /favorites is an alias; guests sign in first.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    $this->customer = shopCustomer($this->shop);
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
});

it('shows the empty wishlist', function (): void {
    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/wishlist'))
        ->assertOk()
        ->assertSee(__st('account.wishlist.empty_title'))
        ->assertDontSee('data-wishlist-card', false);
});

it('lists the favorited products and filters them by name', function (): void {
    $this->shop->tenant->run(fn () => CustomerFavorite::query()->create(['customer_id' => $this->customer->id, 'store_product_id' => $this->shop->own->id]));

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/wishlist'))
        ->assertOk()
        ->assertSee('data-wishlist-remove="'.$this->shop->own->id.'"', false)
        ->assertSee('data-add-to-cart', false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/wishlist?q=zzz-no-match'))
        ->assertOk()
        ->assertDontSee('data-wishlist-remove=', false)
        ->assertSee('data-filter-empty', false);
});

it('redirects the alias and guests', function (): void {
    $this->get(($this->url)('/favorites'))->assertRedirect(($this->url)('/account/wishlist'));
    $this->get(($this->url)('/account/wishlist'))->assertRedirect();
});
