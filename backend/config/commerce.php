<?php

/*
| Storefront cart / checkout / orders (Commerce, decision #68).
*/
return [
    // Minutes an unpaid order keeps its stock reservation before it is cancelled.
    'reservation_minutes' => (int) env('COMMERCE_RESERVATION_MINUTES', 30),

    // Requests per minute per client on /store-api/v1 (cart, checkout, orders, payments).
    'store_api_rate_limit' => (int) env('STORE_API_RATE_LIMIT', 120),

    // Place-order / start-payment attempts per minute per client.
    'checkout_rate_limit' => (int) env('STORE_API_CHECKOUT_RATE_LIMIT', 10),
];
