# Storefront Addresses & Customer Account — Spec (client note 2026-11-25)

Source: old project audit (`/var/www/multi-vendor`, ProfilePage / CheckoutPage / AuthPage / address-modal partials). Client rule: **the checkout address form and the profile address form are the same** and follow the old project's address form. Existing Figma frames are not edited — the canonical form is added as new frames per theme family on page **"Storefront additions"**.

## 1. One canonical address form (all 13 themes, checkout + profile + vendor panel + mobile API)
Base = old Ecommet/Elora modal (`themes/{ecommet,elora}/sections/address-modal.blade.php`). Field order:

| # | Field | Label en / ar | Type | Rule (one FormRequest `SaveCustomerAddressRequest`, used everywhere) |
|---|---|---|---|---|
| 1 | `full_name` | Full name / الاسم الكامل | text | required, max:120 |
| 1b | `label` | Label / تسمية (placeholder "e.g. Home, Work" / مثال: المنزل، العمل) | text, same row as name | nullable, max:50 |
| 2 | `phone` | Phone / الهاتف | intl-tel-input, separate dial code, country pre-selected from visitor country, live validity check | **required**, valid E.164 for the selected country (validated server-side too, libphonenumber) |
| 2b | `email` | Email address / البريد الإلكتروني | email, same row as phone | nullable, email, max:120 (prefilled from customer account; orders use the account email if empty) |
| 3 | "Select address on map" / اختر العنوان على الخريطة | button → browser geolocation + reverse geocode (server-side proxy endpoint, provider configurable in Integrations; fallback IP lookup) fills street/city/state/country; error + "Approximate location detected…" notices | — |
| — | divider "Or enter address manually" / أو أدخل العنوان يدوياً | | |
| 4 | `address_line_1` | Street address / عنوان الشارع (placeholder "Street, building, apartment") | text | required, max:300 |
| 5 | `city` | City / المدينة | text with suggestions from central `cities` of the chosen country (free text allowed) | **required**, max:100 |
| 5b | `state` | State / Region / الولاية / المنطقة | text | nullable, max:100 |
| 6 | `country_id` | Country / الدولة | searchable select (flag + name, search, empty text, clear ×, flips above when no space); only countries the store sells to | required, exists:central countries, active for the tenant |
| 7 | `is_default` | Set as default address / تعيين كعنوان افتراضي | switch | boolean |
| 8 | Save / حفظ · Update address / تحديث العنوان · busy "Saving…" / جارٍ الحفظ… | | |

Titles: checkout "Add / Edit shipping address", "Add / Edit billing address"; profile "New address", "Edit address".
Rules (service `CustomerAddressService`, shared by web, checkout, vendor panel, mobile API):
- first address is default automatically; setting default un-defaults others; deleting the default promotes the oldest remaining.
- `country` name is never stored separately (resolved from `country_id` + translations).
- Souqify family uses **the same fields** in Souqify styling (old Souqify extras — split first/last name, WhatsApp, national address, hard-coded Saudi cities, inert map — are dropped).

## 2. Address list ("Select your address") — checkout & profile
- Card: full name, label chip, Default pill (افتراضي), phone, one-line address; actions: select (checkout radio card), edit, set default (profile), delete (confirm "Delete this address?").
- Checkout: default (else first) pre-selected for shipping; **"Billing address is same as shipping"** checkbox (checked by default) — unchecked shows a second list. **Billing address is stored on the order** (old bug fixed). Empty state "No saved addresses found. Please add a new address." Pay button disabled with reason when no address / shipping unavailable / no gateway.
- Shipping info box under the list: zone/rate label, or error messages:
  - no country → "Please select a shipping country to calculate delivery charges."
  - no zone → "Shipping is not available to your selected country."
  - own products not sold there → "These products are not sold in your shipping country: :products…"
- Country resolution for pricing (old `CustomerCountryResolver` order): selected address → default address → session country. Visibility stays on detected country.

## 2b. Checkout payment method section — **same as old project, no redesign** (client 2026-11-25)
Ported 1:1 from the old project per theme family (markup, texts en/ar, order, logos, states, inline card fields for Stripe / Authorize.Net / 2Checkout, redirect gateways, COD, pay-button labels and disabled reasons). Exact spec: `08-checkout-payment-spec.md`. Designers do not design it; frontend replicates the old markup in Blade + Tailwind with the new backend contracts.

## 3. Customer account pages (every theme family)
| Page | Route | Content |
|---|---|---|
| Login / Register | `/account/login`, `/account/register` | tabs; login (email, password, remember me, **forgot password link**); register (name, email unique, phone intl-tel, password min 8 + confirm); Google / Apple buttons; social error banner; safe `redirect` (same-host only) |
| Forgot / reset password | `/account/forgot-password`, `/account/reset-password/{token}` | **new** (mobile had it) |
| Account › Orders | `/account/orders` | status chips All / Pending / Processing / Shipped / Delivered / Cancelled / Refunded, search (item name, order ID, tracking no.), list with View, Track, Reorder, Cancel (2-step reasons modal), Review (5 stars + comment, after delivery), empty state |
| Account › Profile | `/account/profile` | view + **edit** name, phone, email, language; **change password**; **delete account** (confirm) — new on web |
| Account › Addresses | `/account/addresses` | list + canonical modal (§1, §2) |
| Account › Wishlist / Favorites | `/account/wishlist` (+ `/favorites` alias) | grid, search, empty state — **all families** (old Ecommet lacked it) |
| Account › Returns | `/account/returns`, `/account/returns/{id}` | list (order #, reason, date, status, refund, last note); detail with notes thread, reply (text 10–2000, photos ≤5 MB, video ≤50 MB), withdraw |
| Account › Notifications preferences | `/account/notifications` | email / push toggles per type (new) |
| Order details | `/orders/{uuid}` | details ↔ tracking, items, totals (items subtotal incl. fixed shipping), cancel, reorder, review, request return ("n left"), invoice link — **same actions in all families** |
| Order tracking | `/orders/{uuid}/tracking` | timeline, tracking number, carrier link |
| Request return | `/orders/{uuid}/return` | reason, description, qty, return/exchange, method, replacement variant, note, photos + video |
| Invoice | `/orders/{uuid}/invoice` | printable |
| Logout | POST `/account/logout` | |

Account layout: sidebar (desktop) / drawer + tabs (mobile) per family, like old Elora `partials/account-sidebar`.

## 4. Data
Tenant `customer_addresses`: id, customer_id FK cascade, label, full_name, email, phone (E.164), phone_country (ISO2), address_line_1, city, state, country_id (central id, indexed), latitude, longitude (nullable, from map), is_default, timestamps. Orders snapshot `shipping_address` and `billing_address` JSON.
Central `countries` (all ISO countries: name translations, iso2, iso3, phone_code, currency_code, flag, is_active) and `cities` (country_id, name translations) seeded.

## 5. Delivery
Design (new frames on "Storefront additions", per family Ecommet / Elora / Souqify, desktop 1440 + mobile 375) → backend (Identity customer auth, `CustomerAddressService`, account controllers, countries/cities seeders, tests) → frontend (3 family account layouts + all pages above + checkout address step UI) → QA.
