# Mobile API v1 reference (P11)

The white-label Flutter app talks to two JSON APIs of the **same store** with the **same bearer token**:

| API | Base | Owns |
|---|---|---|
| Mobile API v1 | `/api/mobile/v1` | auth (incl. Google / Apple), bootstrap + static texts, account, addresses, wishlist, notifications inbox, push devices |
| Store API v1 | `/store-api/v1` | catalog (home, categories, products, search), CMS pages + shell, cart, checkout, payments (Stripe inline / PayPal), orders (+ reorder, invoice, cancel reasons), returns, reviews, image search, customer support tickets |

Routes: `app/Modules/Api/routes/api.php` (names `api.mobile.v1.*`) and the `routes/store-api.php` files of Storefront, Commerce, AfterSales, Engagement, Ai, Support (names `store-api.v1.*`). Decisions #104, #106, #108.

## Headers

| Header | Meaning |
|---|---|
| `X-Tenant` | Store id (required). Unknown store → `404 {success:false, message}` |
| `Authorization: Bearer <access_token>` | Signed-in customer (Sanctum, store DB) |
| `X-Locale` | One of the store's switched-on languages (`bootstrap.locale.languages`); else `Accept-Language`; else the store default. Echoed in `Content-Language` |
| `X-Currency` | Display currency only (decision #87: every charge is USD) |
| `X-Country` / `X-Country-Code` | Shopping country ISO2 (bootstrap default country; Store API catalog: only a selling country, else the customer's default address → visitor → store default). Drives which products are sold and their prices |
| `X-App-Platform`, `X-App-Version` | `ios` / `android` + semver, for the update decision (or `platform` / `app_version` query) |
| `X-Cart-Token` | Guest cart (Store API) |

## Envelope

* Success: `{success: true, message, data}`.
* Error: `{success: false, message}` with the HTTP status (401 unauthenticated / expired, 403, 404 not found or another customer's row, 429 throttled with `Retry-After`).
* Validation: `422 {message, errors: {field: [..]}}` (Laravel shape).
* Lists: `data.items` + `data.meta {current_page, last_page, per_page, total}`; `page`, `per_page` (1–50, default 20).
* Money (Store API + wishlist): `{amount (USD cents), currency: "USD", formatted, display: {currency, amount, formatted} | null}`.

## Rate limits

`mobile-api` = Admin › Settings › Platform › Checkout & API "Store API requests per minute" (per store + customer, else IP). `mobile-auth` = 10 / min per store + IP on register / login / refresh / password codes; sign-in also 5 failed tries / min per e-mail + IP. Code resend 6 / min, verify 10 / min.

## Tokens

Sign-in / sign-up return `data.tokens {token_type, access_token, expires_at, expires_in, refresh_token, refresh_expires_at}`.
* Access token: ability `api`, 24 h, valid on both APIs.
* Refresh token: ability `auth:refresh`, 60 days, accepted only by `POST auth/refresh` (rotates: the old pair is revoked).
* Both are named `mobile:<device_id>`; sign-out removes that pair; password change revokes the other installs; password reset revokes all.
* Tokens live in the store DB — another store never accepts them.

## Endpoints (`/api/mobile/v1`)

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `ping` | – | health |
| GET | `bootstrap` | – | store, branding (theme slug/family/variant, primary colour, fonts, splash), locale + languages, currencies (base/charge USD, display list), countries, feature flags, push availability, `auth.social_providers` (only configured: `google`, `apple`), `app.update.state` none/optional/required + store URL, maintenance + message, `texts.version` |
| GET | `texts?group=mobile` | – | resolved static texts (store edits → base → English) of `X-Locale`; `ETag` / `If-None-Match` → 304 |
| POST | `auth/register` | – | name, email, phone + phone_country (intl), password + confirmation, device_id → 201 customer + tokens; sends the e-mail code |
| POST | `auth/login` | – | email, password, device_id |
| POST | `auth/refresh` | – | refresh_token → new pair |
| POST | `auth/social/{google\|apple}` | – | `id_token` from the native SDK, `nonce` (raw; Apple may carry its SHA-256), `name` (Apple first sign-in), `device_id` → `{customer, tokens}`; 201 when the account was created, 200 when found by provider id or linked by verified e-mail; 404 provider not configured; 422 `id_token` invalid / unverified e-mail of an existing account / inactive |
| POST | `auth/password/forgot` | – | email → 6-digit code by mail (never reveals existence), resend after 45 s |
| POST | `auth/password/check` | – | email, code |
| POST | `auth/password/reset` | – | email, code, password + confirmation |
| POST | `auth/logout` | token | `everywhere: true` signs out all installs |
| GET | `auth/email/verification` | token | verified, resend_in |
| POST | `auth/email/verify` | token | code (6 digits) |
| POST | `auth/email/resend` | token | |
| GET | `me` | token | customer |
| PUT | `account/profile` | token | name, email, phone, phone_country, locale |
| PUT | `account/password` | token | current_password, password + confirmation |
| GET/PUT | `account/notification-preferences` | token | `{preferences: {type: {email, push}}}` partial update |
| DELETE | `account` | token | password; anonymises + soft deletes |
| GET | `account/addresses` | token | addresses + shipping countries |
| POST | `account/addresses` | token | SaveCustomerAddressRequest (intl phone) |
| PUT | `account/addresses/{id}` | token | |
| POST | `account/addresses/{id}/default` | token | |
| DELETE | `account/addresses/{id}` | token | |
| GET | `favorites` | token | product cards (USD + display), `ids` of all favorites |
| POST | `favorites` | token | store_product_id (idempotent) |
| DELETE | `favorites/{storeProductId}` | token | |
| GET | `notifications?unread=1` | token | inbox items (translated title/body, event, type, order_uuid, ticket_id, read) + unread_count |
| GET | `notifications/unread-count` | token | |
| POST | `notifications/{uuid}/read` | token | |
| POST | `notifications/read-all` | token | |
| DELETE | `notifications/{uuid}` | token | |
| POST | `devices` | optional | device_id, platform ios/android, push_token, push_enabled, app_version, os_version, model; guest installs allowed, bound to the customer on sign-in |
| GET | `devices` | token | the customer's installs |
| DELETE | `devices/{device_id}` | optional | own or guest install only |

## Store API catalog + content (`/store-api/v1`, decision #106)

Public (a token only adds `favorited`). Same services as the themes, so prices equal the storefront and the cart (flash sales included). Product cards: `{id, name, slug, url, image, image2, price, compare_price, discount_percent, flash {percentage, starts_at, ends_at}|null, rating, review_count, in_stock, stock_left, has_variants, favorited, badge, sold_count, weight, star_seller}`.

| Method | Path | Notes |
|---|---|---|
| GET | `home` | `theme {slug, family, variant}`, `country`, `currency`, `sections`: `hero` / `promo` banners, `deals`, `clearance`, `tabs`, `interests` (listing), `flash_sale {id, title, percentage, starts_at, ends_at, banner_url}`, `best_selling` / `top_rated` / `new_in` (listings); Elora + Souqify add `categories`, `trending`, `top_rated_rail`, `new_rail`, `offers`, `recommended` (rows), `badges [{id, name, items}]` |
| GET | `home/tabs/{tab}?page` | `recommended` or a top-level category slug (404 unknown) |
| GET | `categories` | tree `items [{id, parent_id, name, slug, description, image, image_webp, featured, show_in_menu, children}]` (central shown + store-own, store order, hidden central left out) + `menu_ids` |
| GET | `categories/{slug}` | category + children + `breadcrumbs` |
| GET | `products` | `kind` new_in / best_selling / top_rated / offers, or `category` (slug); `sort` (per kind: recommended, relevance, best_selling, newest, price_asc, price_desc, rating, discount), `page`, `per_page` 1–50, `period` 7/14/30, `price_min` / `price_max` (USD cents), `rating` 1–5, `in_stock`, `flash`, `added` 7/30, `discount` 10/20/30/50, `categories[]`, `options[attributeId][]`. → `items`, `meta`, `facets {price, rating, options, categories, in_stock}`, `sort`, `sorts` |
| GET | `products/{id}?variant=` | details: images (image URLs only, unchanged), `media [{type: image|video, url, poster}]` (whole gallery in order, videos included, `poster` only for videos and may be null; #114 — Flutter should render `media` with a native player for videos, contained, swipeable), price / compare_price, flash, stock, `attributes` (pickers), `variants [{id, label, options {attributeId: optionId}, price, compare_price, discount_percent, in_stock, stock, image}]`, `selected_variant_id`, `category`, `breadcrumbs`, `delivery {label, min_date, max_date, company, cutoff_minutes, location}`, `delivery_days`, `free_shipping_grams`, `reviews {average, count, distribution}`, `related`. 404 when not sold in the country. Reviews list: `products/{id}/reviews` |
| GET | `search?q=` | listing filters + `term`; no results → `popular` |
| GET | `search/autocomplete?q=&limit=` | `products` (cards) + `categories [{id, name, slug}]` from 2 characters |
| GET | `pages` / `pages/{slug}` | published CMS pages (`slug, title, type, group, show_in_footer, is_contact`, detail adds `body` HTML + `meta_description`) |
| GET | `shell` | `header_links`, `menu` (category tree), `footer {columns, legal, about, copyright}`, `social [{network, label, icon, url}]`, `promo_bar`, `payment_badges` |

## Store API order extras

| Method | Path | Notes |
|---|---|---|
| GET | `orders/cancellation-reasons` | `[{value, label, requires_note}]` (customer list) |
| POST | `orders/{uuid}/cancel` | `reason_code` (from the list, optional), `reason` (note, required for `other`) |
| POST | `orders/{uuid}/reorder` | re-adds every line through the add-to-cart rules at today's prices → `cart`, `added`, `skipped [{store_product_id, variant_id, name, reason, message}]`; nothing addable → 422 `reorder_unavailable`. Guests' cart token rules as `cart/items` |
| GET | `orders/{uuid}/invoice` | `{url, expires_at}`: 30-min signed link to the printable invoice on the store domain (open in a browser / web view; print or save as PDF) |

## Store API customer support tickets (decision #108)

Signed-in customer only (401); another customer's ticket → 404. Ticket: `{id, reference (C-1001), subject, topic {value, label}, status {value, label, tone}, order {uuid, number}|null, unread, last_reply_by customer|store, last_reply_at, excerpt, can_reply, can_close, created_at}`; detail adds `messages [{id, author customer|store, author_name, body, created_at, attachments [{id, name, mime_type, size, size_label, is_image, url}]}]` (file URLs are 30-min signed links, see below).

| Method | Path | Notes |
|---|---|---|
| GET | `support/tickets/options` | `topics [{value, label}]`, the customer's recent `orders [{uuid, number}]`, `max_files` 5, `max_file_kb` 10240, `file_types` |
| GET | `support/tickets?status=active\|closed&page&per_page` | `items`, `meta` |
| POST | `support/tickets` | multipart: `topic`, `subject` (3–150), `body` (5–5000), `order_uuid` (own order, optional), `files[]` (≤5 × 10 MB; jpg/png/webp/gif/pdf/office/csv/txt/zip) → 201 `ticket` (detail). Store staff notified |
| GET | `support/tickets/{id}` | detail; marks store replies read |
| POST | `support/tickets/{id}/messages` | `body` and/or `files[]` → 201 `message` + `ticket`; a resolved ticket reopens; closed → 422 |
| POST | `support/tickets/{id}/close` | → `ticket`; already closed → 422 |

Store replies / status changes reach the customer as inbox rows (`event` `ticket_replied` / `ticket_resolved` / `ticket_closed` / `ticket_reopened`, `type` `support`, `ticket_id`), e-mail per the `support` switch and push.

## Signed file links (RQA-APP-016, decision #128)

Customer-ticket attachments (`support/tickets/{id}/attachments/{file}`) and return evidence (`returns/{id}/media/{media}`) are 30-min relative signed URLs that name their store in a signed query parameter: `…?expires=…&tenant={store id}&signature=…`. The app loads them as-is (Image.network, in-app browser) — **no `X-Tenant`, no bearer token**; the platform host or the store domain both work. A valid signed `tenant` wins over the `X-Tenant` header / host; tampered or expired links → 403 (404 when no store can be identified at all), links without `tenant` or of another store → 403. Request a fresh detail to get new links after expiry. `orders/{uuid}/invoice` keeps its link on the store domain.

## Push

Every customer **database** notification (orders, shipping, returns, refunds, support tickets) is also pushed by `Notifications\Listeners\QueueCustomerPush` → `SendCustomerPush` job → FCM HTTP v1, with the project id + service account JSON from **Admin › Integrations › Firebase** (DB). Text in the customer's language; sent only when the customer's push switch of the type is on; data payload `{notification_id, event, order_uuid, ticket_id}` for deep links. Rejected tokens are cleared.

## Store settings (DB)

`store_settings.mobile_app` (`Api\Services\MobileAppSettingsService`): `ios` / `android` `{min_version, latest_version, store_url}`, `maintenance`, `maintenance_message {locale}`, `splash_background`, `splash_logo_path`, `features {reviews, wishlist, returns, image_search, guest_checkout, currency_switcher, push_notifications}`. Edited in Vendor › Your store › Mobile app (VEN-037, `store.mobile-app.manage`) and Admin › Vendors › profile › Mobile app tab (ADM-041g, `vendors.manage`) — `MobileAppPanelService` + `MobileAppSettingsRequest` (decision #108).

## Social sign-in configuration

Admin › Integrations › Google / Apple (DB, decision #69): the provider switch + Client ID / Services ID, and **Shopper app client IDs / bundle IDs** (`customer_client_ids`, comma or line separated) — accepted `aud` values of app id_tokens besides the Client ID. Apple keys are fetched from `https://appleid.apple.com/auth/keys` and cached 12 h.
