# Mobile API v1 reference (P11)

The white-label Flutter app talks to two JSON APIs of the **same store** with the **same bearer token**:

| API | Base | Owns |
|---|---|---|
| Mobile API v1 | `/api/mobile/v1` | auth, bootstrap + static texts, account, addresses, wishlist, notifications inbox, push devices |
| Store API v1 | `/store-api/v1` | cart, checkout, payments (Stripe inline / PayPal), orders, returns, reviews, image search |

Routes: `app/Modules/Api/routes/api.php` (names `api.mobile.v1.*`) and the `routes/store-api.php` files of Commerce, AfterSales, Engagement, Ai (names `store-api.v1.*`). Decision #104.

## Headers

| Header | Meaning |
|---|---|
| `X-Tenant` | Store id (required). Unknown store → `404 {success:false, message}` |
| `Authorization: Bearer <access_token>` | Signed-in customer (Sanctum, store DB) |
| `X-Locale` | One of the store's switched-on languages (`bootstrap.locale.languages`); else `Accept-Language`; else the store default. Echoed in `Content-Language` |
| `X-Currency` | Display currency only (decision #87: every charge is USD) |
| `X-Country` / `X-Country-Code` | Shopping country ISO2 (bootstrap default country, storefront index country) |
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
| GET | `bootstrap` | – | store, branding (theme slug/family/variant, primary colour, fonts, splash), locale + languages, currencies (base/charge USD, display list), countries, feature flags, push availability, `app.update.state` none/optional/required + store URL, maintenance + message, `texts.version` |
| GET | `texts?group=mobile` | – | resolved static texts (store edits → base → English) of `X-Locale`; `ETag` / `If-None-Match` → 304 |
| POST | `auth/register` | – | name, email, phone + phone_country (intl), password + confirmation, device_id → 201 customer + tokens; sends the e-mail code |
| POST | `auth/login` | – | email, password, device_id |
| POST | `auth/refresh` | – | refresh_token → new pair |
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
| GET | `notifications?unread=1` | token | inbox items (translated title/body, event, type, order_uuid, read) + unread_count |
| GET | `notifications/unread-count` | token | |
| POST | `notifications/{uuid}/read` | token | |
| POST | `notifications/read-all` | token | |
| DELETE | `notifications/{uuid}` | token | |
| POST | `devices` | optional | device_id, platform ios/android, push_token, push_enabled, app_version, os_version, model; guest installs allowed, bound to the customer on sign-in |
| GET | `devices` | token | the customer's installs |
| DELETE | `devices/{device_id}` | optional | own or guest install only |

## Push

Every customer **database** notification (orders, shipping, returns, refunds) is also pushed by `Notifications\Listeners\QueueCustomerPush` → `SendCustomerPush` job → FCM HTTP v1, with the project id + service account JSON from **Admin › Integrations › Firebase** (DB). Text in the customer's language; sent only when the customer's push switch of the type is on; data payload `{notification_id, event, order_uuid}` for deep links. Rejected tokens are cleared.

## Store settings (DB)

`store_settings.mobile_app` (`Api\Services\MobileAppSettingsService`): `ios` / `android` `{min_version, latest_version, store_url}`, `maintenance`, `maintenance_message {locale}`, `splash_background`, `splash_logo_path`, `features {reviews, wishlist, returns, image_search, guest_checkout, currency_switcher, push_notifications}`. The vendor (VEN-037) and admin (ADM-041g) pages write it.
