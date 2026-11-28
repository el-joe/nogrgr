<?php

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Storefront phone fields pre-select the visitor's geo country; without a geo result,
| the ship-to country the shopper sees (never the browser language ahead of it).
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    tenancy()->end();
    $this->register = $this->tenantUrl($this->shop->tenant, '/account/register');
});

it('pre-selects the phone country', function (array $headers, ?string $shipTo, string $expected): void {
    if ($shipTo !== null) {
        $this->withSession(['storefront.country_id' => $this->shop->{$shipTo}->id]);
    }

    $this->get($this->register, $headers)
        ->assertOk()
        ->assertSee('name="phone_country" value="'.$expected.'"', false);
})->with([
    'geo result wins' => [['CF-IPCountry' => 'EG'], 'ae', 'EG'],
    'no geo: picked ship-to country' => [['Accept-Language' => 'en-GB'], 'ae', 'AE'],
    'no geo: store default ship-to country' => [['Accept-Language' => 'en-GB'], null, 'SA'],
]);
