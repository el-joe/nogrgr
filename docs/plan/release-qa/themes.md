# Release QA — storefront themes (qa-themes)

Final release test cycle, 2026-11-27. Scope: the 13 storefront themes (Ecommet, Elora 1–6, Souqify 1–6), the custom Twig starter theme, the central theme gallery `/themes` + demo-store previews, and the full shopper flow with money checks against `/var/www/multi-vendor/docs/ORDER_FORMULAS.md`.

## Setup

- QA DB `nogrgr_qa` (tenant DB suffix `_qa`), `php artisan serve --host=127.0.0.1 --port=8203 --no-reload`, redis cache prefix `qathemes_` + redis queue prefix `qathemes_db_` with its own `queue:work redis` worker, log mailer, `TENANT_BASE_DOMAIN=localhost`, `CENTRAL_DOMAINS=localhost,127.0.0.1`.
- Store under test: **qastore2** (tenant 2, `http://qastore2.localhost:8203`), filled with `php artisan storefront:demo-store qastore2`; its `store_theme` setting was switched to each of the 13 catalogue themes in turn. Tenant 8 `theme-demo` (platform demo store) was left to qa-app and only read for the gallery previews. qastore1 belongs to qa-vendor.
- QA data changes made for the test: Stripe + COD rows in qastore2's `store_payment_gateways` (Stripe uses the platform test keys); SAR / EGP rates set as if the daily sync had run (3.75 / 48.5, `rate_synced_at` = now) so the display-currency path could be exercised; central product 7 put out of stock (back-in-stock form), restored afterwards; test accounts: shopper `qa-themes-shopper@example.test` (registered through the storefront), admin `qa-themes@qa.test` (super-admin), vendor staff `qa-owner2@example.test` password set. Passwords only in the session scratchpad.
- Headless Chromium (Playwright 1.65) driven by the scripts in `backend/tests/e2e/themes/` (see "Repeatable scripts"). Screenshots: `docs/plan/release-qa/themes/<theme>/`. Figma reference frames (read through the Figma MCP, read-only): `docs/plan/release-qa/themes/figma/`.
- Payments: Stripe in test mode, inline Card Element. PayPal has no credentials on the QA DB, so it is not offered (not tested). COD is never listed at checkout (client decision, `StorefrontGatewayService`), so there is no COD storefront order to test.

## Repeatable scripts (`backend/tests/e2e/themes/`)

Run from `backend/` with `NODE_PATH=<dir containing playwright>`; env `QA_BASE`, `QA_TENANT_DB`, `QA_STATE` (storage-state JSON), `QA_PW_FILE`, `QA_PHOTO`.

| Script | What it does |
| --- | --- |
| `lib.mjs` | theme switch (`store_settings.store_theme`), browser modes (1440 en light / 1440 ar dark / 768 en / 375 ar light / 375 en dark), DOM audit (overflow + offender, raw translation keys, placeholder copy, dead `#` links, `object-fit: cover`, broken images, fonts, `dir`, theme family), console / page / HTTP / request failures |
| `crawl.mjs [slug…]` | 19 storefront pages × 5 modes per theme, audit + screenshots |
| `all-themes.sh [slug…]` | per theme: checkout (Stripe 4242), rail drag, shopper flow at 1440 en + 375 ar |
| `run.mjs <flow> [mode]` | step runner for the flows below |
| `flows/register.mjs`, `login.mjs` | storefront sign-up (intl-tel-input), sign-in |
| `flows/checkout.mjs` | product → cart (coupon `QA_COUPON`) → checkout → address modal (intl phone, country combobox) → Stripe card `QA_CARD` (4242… / 4000 0000 0000 0002) → success page |
| `flows/shopper.mjs` | rail mouse-drag, reduced motion, search autocomplete, image search modal + upload, category sort + filter, product (variant price, delivery estimate, reviews, video + floating video), back-in-stock form, add to cart + mini cart, wishlist, orders / order / tracking / invoice, reorder, cancel, return with photo, support ticket, verify-email page, language switch |
| `flows/rails.mjs` | drags every movable Swiper / native rail with the mouse |
| `flows/currency.mjs` | display currency SAR → checkout "You will be charged $X USD" line |
| `flows/money-panels.mjs` | the same order in the vendor panel and the admin panel |
| `flows/gallery.mjs`, `demo-readonly.mjs` | central `/themes` + every preview, demo store read-only |

## Defects (RQA-SF-0xx)

### RQA-SF-001 — High — Store API ignored the storefront language (FIXED, re-tested)
- Steps: storefront in English (cookie `locale=en`, or a signed-in customer whose saved language is en) in a browser that sends no / an Arabic `Accept-Language`; place an order at /checkout; open a return form and submit it empty; add to cart on the demo store.
- Expected: Store API answers follow the page language (order `locale`, item name snapshot, validation messages, toasts).
- Actual: `SetStoreApiLocale` read only `X-Locale` / `Accept-Language` (fallback `ar`) and the storefront JS sent no `X-Locale`. ORD-10004 placed from an English page got `locale = ar` and the item name "هاتف ذكي 6.5 بوصة" on the success page, vendor order and admin order; the return form showed "حقل السبب مطلوب." on an English page; the demo-store notice came in Arabic.
- Fix (fixer qa-themes-fix1): `resources/js/core/http.js` sends `X-Locale` = `<html lang>` on every request (+ the raw `fetch` in `storefront/catalog/search.js`); `SetStoreApiLocale` order: `X-Locale` → (session storefront calls) signed-in customer's saved locale → `locale` cookie → `Accept-Language` → default. Mobile / Bearer clients unchanged.
- Test: `tests/Feature/Api/StoreApiLocaleTest.php` (4). Browser re-test: ORD-10006 from an English page → `locale = en`, item "6.5-inch Smartphone".

### RQA-SF-002 — Medium — "We accept" / payment-method badges listed methods checkout does not offer (FIXED, re-tested)
- Steps: store with active Stripe + COD rows (COD is never offered at checkout, client decision); open any page footer, cart or checkout summary, product trust row.
- Expected: badges = what checkout offers. Actual: "Cash on delivery" badge shown (text overflowing its 56×37 box: "delivery" spilled below), and any active store row was badged even without central keys.
- Fix: `StorefrontShell::paymentBadges()` builds from `StorefrontGatewayService::options()`; text badge clamped to its box (`pay-badges.blade.php`, `footer/ecommet.blade.php`).
- Test: `tests/Feature/Storefront/PaymentBadgesTest.php` (3). Browser re-test: cart page has no "Cash on delivery" badge.

### RQA-SF-003 — Medium — Arabic prices used Arabic-Indic digits against the "Latin digits everywhere" rule (FIXED)
- Steps: any storefront page in Arabic (all 13 themes), Store API `formatted` with `X-Locale: ar`.
- Expected: Latin digits in every language (`App\Shared\Support\Locale::numberLocale()`, Figma RTL frame 56:17016), like the countdowns, weights and ratings on the same card.
- Actual: `Money::format()` and `Commerce\Support\MoneyPresenter` used `NumberFormatter(app()->getLocale())` → "‏١٣٧٫٠٨ US$" next to Latin "280g", "05 : 06 : 01" — mixed numerals on one card, also in cart, checkout "Pay …", order pages, invoices, e-mails and Arabic panels.
- Fix (fixer qa-themes-fix2): both format through `Locale::numberLocale()`; panel `admin/products.js` stock total uses `numberLocale()`. English output unchanged ("$137.08").
- Tests: `tests/Unit/Shared/MoneyEdgeCasesTest.php` (new case), `tests/Unit/Shared/MoneyPresenterLocaleTest.php`. Re-test: Store API `X-Locale: ar` → "‏768.35 US$", display "‏2,881.31 ر.س.‏".

### RQA-SF-004 — Medium — two storefront display-currency tests were stale after QA-FINAL-009 (FIXED, tests only)
- `CheckoutPagesTest` "shows the display-currency total … (decision #87)" and `StoreApiCatalogTest` "converts the display currency but keeps USD amounts" created SAR without `rate_synced_at`; since QA-FINAL-009 (611a35e) a never-synced currency is hidden, so both failed. The tests now create a synced rate. Behaviour verified in the browser: with SAR synced (3.75), checkout shows "Pay SAR 8,718.38" and "You will be charged $2,324.90 USD".
