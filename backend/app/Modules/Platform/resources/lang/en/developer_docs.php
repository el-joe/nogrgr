<?php

/*
 * Admin › System tools › Developer docs (ADM-115). Describes the platform as built:
 * surfaces, Store API v1, payment webhooks, the mobile API and storefront themes.
 */
return [
    'title' => 'Developer docs',
    'topics_title' => 'Topics',
    'updated' => 'Updated :date',
    'copy' => 'Copy',
    'breadcrumb_system' => 'System',
    'topics' => [
        'getting-started' => [
            'title' => 'Getting started',
            'summary' => 'Nogrgr is one Laravel application that serves several surfaces. Each request is routed to a surface by its host and path, and store traffic runs inside the store\'s own tenant database.',
            'sections' => [
                [
                    'heading' => 'Surfaces',
                    'items' => [
                        'Central website and admin panel on the platform domain (`/admin/...`).',
                        'Vendor panel on the store domain (`/admin/...`) or on the platform domain (`/tenant/{store}/...`).',
                        'Storefront on the store domain or sub-domain, one of 13 themes per country.',
                        'Store API `/store-api/v1` (JSON) for themes and the mobile app.',
                        'Mobile API `/api/mobile/v1` with the store chosen by the `X-Tenant` header.',
                    ],
                ],
                [
                    'heading' => 'Data and money',
                    'body' => [
                        'The central database holds the platform catalogue, plans, ledger and reporting facts; every store has its own database for its customers, carts and orders.',
                        'All amounts are stored in USD as integer cents and every payment is charged in USD. Other currencies are display conversions only (send `X-Currency` to see them).',
                    ],
                ],
                [
                    'heading' => 'Responses',
                    'body' => ['JSON endpoints answer with one envelope. Validation errors use HTTP 422 and list the fields in `errors`.'],
                    'code' => "{\n  \"success\": true,\n  \"message\": \"pong\",\n  \"data\": { \"tenant\": 42, \"version\": \"v1\" }\n}",
                ],
            ],
            'note' => 'Every panel route requires a signed-in user and a permission; JSON routes answer 401 / 403 instead of redirecting.',
        ],
        'store-api' => [
            'title' => 'Store API v1',
            'summary' => 'The Store API powers carts, checkout, orders, payments, returns and reviews for the storefront themes and the mobile app. Base path: `/store-api/v1`.',
            'sections' => [
                [
                    'heading' => 'Choosing the store',
                    'body' => ['On the store domain the store comes from the host and the theme uses the session with its CSRF token. From an app, send the store in the `X-Tenant` header and authenticate the customer with a Sanctum bearer token.'],
                    'code' => "GET /store-api/v1/cart\nX-Tenant: 42\nX-Cart-Token: 9f1c...\nX-Currency: SAR\nAccept: application/json",
                ],
                [
                    'heading' => 'Headers',
                    'items' => [
                        '`X-Cart-Token` keeps a guest cart between requests; the token is returned with the cart.',
                        '`X-Currency` switches the display currency; amounts are still charged in USD.',
                        '`Authorization: Bearer <token>` signs the customer in (checkout, orders, payments and returns need it, otherwise 401).',
                    ],
                ],
                [
                    'heading' => 'Endpoints',
                    'items' => [
                        '`GET /cart`, `POST /cart/items`, `PATCH /cart/items/{item}`, `DELETE /cart/items/{item}`',
                        '`POST /cart/coupon`, `DELETE /cart/coupon`, `PUT /cart/country`',
                        '`GET /checkout/options`, `POST /checkout/place`',
                        '`GET /orders`, `GET /orders/{uuid}`, `POST /orders/{uuid}/cancel`',
                        '`POST /payments/{uuid}/start`, `POST /payments/{uuid}/confirm`',
                        '`GET|POST /orders/{uuid}/returns`, `GET /returns`, `GET /returns/{return}`, `POST /returns/{return}/messages`, `POST /returns/{return}/withdraw`',
                        '`GET|POST /products/{storeProduct}/reviews`, `POST /orders/{uuid}/items/{item}/review`',
                    ],
                ],
                [
                    'heading' => 'Rate limits',
                    'body' => ['The API allows 120 requests per minute and checkout / payment start 10 per minute by default. Both limits are set in Admin › Settings › Platform › Checkout & store API.'],
                ],
            ],
            'note' => 'Prices in responses are computed by the order pricing engine; never recalculate totals on the client.',
        ],
        'webhooks' => [
            'title' => 'Payment webhooks',
            'summary' => 'Payment gateways report captured, failed and refunded payments through signed webhooks. Configure these URLs in each gateway dashboard.',
            'sections' => [
                [
                    'heading' => 'Endpoints',
                    'items' => [
                        'Platform payments (registrations, subscriptions, settlements, add-ons and store checkouts on the platform keys): `POST {platform-domain}/webhooks/payments/{gateway}`.',
                        'Store checkouts on the store\'s own gateway keys: `POST {store-domain}/store-api/v1/payments/webhooks/{gateway}`.',
                    ],
                ],
                [
                    'heading' => 'Gateways',
                    'body' => ['`{gateway}` is one of: cod, stripe, paypal, paymob, tap, myfatoorah, moyasar, tabby, tamara. An unknown gateway answers 404.'],
                ],
                [
                    'heading' => 'Signatures and retries',
                    'body' => [
                        'The gateway driver verifies the signature with the secret saved in Admin › Integrations; a bad signature answers 400 so the gateway retries.',
                        'Every event is recorded once: a replayed event answers 200 with `duplicate`, and events about checkouts the platform does not know answer 200 with `ignored`, so the gateway stops retrying.',
                    ],
                    'code' => "HTTP/1.1 200 OK\n{\n  \"received\": true,\n  \"result\": \"processed\"\n}",
                ],
            ],
            'note' => 'Webhook routes are CSRF-exempt and throttled; they never trust amounts from the payload without the gateway signature.',
        ],
        'mobile-api' => [
            'title' => 'Mobile API',
            'summary' => 'The white-label mobile app talks to one store. It uses the mobile API for app-level calls and the Store API for shopping.',
            'sections' => [
                [
                    'heading' => 'Base URLs',
                    'items' => [
                        'Mobile API: `/api/mobile/v1` (route names `api.mobile.v1.*`).',
                        'Shopping: `/store-api/v1` with the same `X-Tenant` header.',
                    ],
                ],
                [
                    'heading' => 'Health check',
                    'body' => ['Call the ping endpoint on start-up to confirm the store id baked into the build is reachable.'],
                    'code' => "GET /api/mobile/v1/ping\nX-Tenant: 42\n\n{\n  \"success\": true,\n  \"message\": \"pong\",\n  \"data\": { \"tenant\": 42, \"version\": \"v1\" }\n}",
                ],
                [
                    'heading' => 'Customers',
                    'body' => ['Signed-in customer calls carry a Sanctum bearer token; guests send `X-Cart-Token`. Send `X-Locale: ar` (or `Accept-Language`) for translated messages.'],
                ],
            ],
            'note' => 'Each store build is configured with its own name, bundle id, icons, theme tokens, API base URL and store id; the same API serves every build.',
        ],
        'themes' => [
            'title' => 'Storefront themes',
            'summary' => 'Stores choose from 13 themes in three families: Ecommet (one home) and Elora and Souqify (six homes each). A store can use a different theme per selling country.',
            'sections' => [
                [
                    'heading' => 'Theme resolution',
                    'body' => ['The active theme is saved in the store setting `store_theme`. A country without its own entry uses the default; a missing or deactivated theme falls back to the store\'s registration family and then to Ecommet.'],
                    'code' => "{\n  \"default\": \"ecommet\",\n  \"countries\": { \"191\": \"elora-3\" }\n}",
                ],
                [
                    'heading' => 'Slugs',
                    'items' => [
                        '`ecommet`',
                        '`elora-1` … `elora-6`',
                        '`souqify-1` … `souqify-6`',
                    ],
                ],
                [
                    'heading' => 'Availability',
                    'body' => ['Vendors pick a theme per country and its home variant in Vendor panel › Your store › Themes (`/admin/store/themes`), with a live preview of each theme before activating it. Only active themes available in the country are offered.'],
                ],
            ],
            'note' => 'Theme pages read prices, carts and checkout from the Store API and the pricing engine, so every theme shows the same totals.',
        ],
    ],
];
