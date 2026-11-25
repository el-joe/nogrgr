<?php

/*
| Storefront cart / checkout / orders (Commerce, decision #68). Hard-coded defaults;
| Admin › Settings › Platform › Commerce overrides them at runtime (decision #69).
*/
return [
    // Minutes an unpaid order keeps its stock reservation before it is cancelled.
    'reservation_minutes' => 30,

    // Requests per minute per client on /store-api/v1 (cart, checkout, orders, payments).
    'store_api_rate_limit' => 120,

    // Place-order / start-payment attempts per minute per client.
    'checkout_rate_limit' => 10,
];
