# Checkout conditions and numbers: old project vs new (be-qa, round 2)

Old sources read: `CheckoutPage`, `CartPage`, `CustomerCountryResolver`, `CountryDetectorService`, `ApplyStorefrontSessionContext`, `CalculatesFreeShipping`, `OrderComposer`, `PlacesOrders`, `App\Support\OrderPricing`, `ShippingEstimateService`, `CouponCatalog`, `docs/ORDER_FORMULAS.md`. Nothing was copied.

## 1. Formulas, same inputs, old result vs new result (USD)

The old class `App\Support\OrderPricing` was run next to `Commerce\Pricing` with the same inputs: 0 differences. Locked by `tests/Unit/Commerce/OldProjectFormulaParityTest.php`.

| Case | Inputs | Lines | Coupon | Order total | Platform | Vendor | Return of 1 unit |
|---|---|---|---|---|---|---|---|
| ORDER_FORMULAS example | cost 1.72 +25 %, qty 1, fixed 200, flash 10 % central, ship 1.20 | 181.93 | 0 | 183.13 | 182.70 | 0.43 | 181.93 |
| ORD-10001 (live) | cost 0.95 +25 %, qty 3, ship 5, store coupon 10 % | 3.57 | 0.36 | 8.21 | 7.85 | 0.36 | 1.07 |
| Mixed rules | 7.35 + 2.15 fixed x3, fixed 1.37, flash 15 % store; 0.99 +33.5 % x7, fixed 0.45; ship 12.49; platform coupon 5 | 25.39 / 9.69 | 5.00 | 42.57 | 38.29 | 4.28 | 7.26 |
| Half-cent rounding | 10.05 +10 %, flash 50 % central, store coupon 12.5 % | 5.53 | 0.69 | 4.84 | 4.52 | 0.32 | 4.84 |
| Coupon above items | 1.00 x2, ship 4, store coupon 50 | 2.00 | 2.00 | 4.00 | 6.00 | -2.00 | 0.00 |

Both sides: old and new values are identical in every cell.

## 2. Conditions

| Condition | Old | New before this round | Now |
|---|---|---|---|
| Visitor country detection | CF-IPCountry, X-Country-Code, cached, Accept-Language region | headers, else configured default | Accept-Language region added before the configured default |
| Price country (cart, checkout, product, listing) | switcher / detected country, never the saved address | switcher, then **default address**, then detected | **Fixed**: switcher, detected, store default |
| Shipping zone country | selected address, default address, session country | selected address, cart's stored country, default address, **store default** | **Fixed**: selected address, default address, shopper country |
| Store API vs server pages | one resolver | Store API quoted the store default (EG) while pages quoted the visitor (SA): header drawer $1.75, cart page $6.75 | **Fixed**: theme session calls and `X-Country` use the same country as the pages; the context country is no longer written onto the cart |
| "Sold in countries" visibility of own products | detected country, ignores manual pick | switcher country | **Fixed** |
| Own products not sold in shipping country | refused, "These products are not sold in your shipping country: …" | same | same |
| No country / no zone | "Please select a shipping country…" / "Shipping is not available to your selected country." | same states | same |
| Shipping lines | catalog, physical (`requires_shipping`, not digital); own ship free | digital central products were charged and could block checkout | **Fixed** |
| Zone / rate choice | active zone, first rate min..max by weight, none = free | rule, zone (city / whole country), first band, none = free (decision #63) | same result for one zone |
| Weight | variant else product grams x qty | same | same |
| Fixed shipping | per line, not x qty, rate per gram of the price country | same | same (price country fixed above) |
| Shipping split on lines | by weight, else qty, last line takes the rest | same | same |
| Mixed cart | two orders, shipping on the catalog order, coupon split by items total | same | same |
| Flash sale + coupon | flash per line, coupon on items total after flash, not on shipping | same | same |
| Coupon country | shipping country | cart country | **Fixed** (shipping country) |
| Coupon below minimum spend | removed automatically with a message | kept, checkout blocked until removed | **Fixed** (removed, reason shown, order can be placed) |
| Plan "orders per month" | "This store cannot accept new orders at this time…" | not enforced anywhere | **Fixed** (`store_order_limit` block, en/ar) |
| Billing address | typed on checkout, not stored on the order (old bug) | stored | kept |
| Pay button disabled | no address, no shipping, no gateway | same plus items / coupon | same |
| "Add a shipping address" note | live | stayed visible after the address was saved | **Fixed** |
| Estimated delivery | country day rule + stop periods | same (Shipping module) | same |
| Charge currency | USD | USD (decision #87) | same |
| Stored on order | totals, split, coupon, shipping address | plus billing, quote, delivery dates, display rate | same |

Numbers checked in the browser for one cart: product page 1.19, cart / checkout / order / invoice / e-mail subject / vendor and admin order / ledger: items 3.57, shipping 5.00, coupon 0.36, total 8.21, platform 7.85, store 0.36.

## 3. Differences kept on purpose, for the client to decide

| Old | New (kept) | Why |
|---|---|---|
| Out-of-stock lines only warned at place order; the order was still taken | refused, stock reserved at placement (decision #68) | taking it oversells; say if overselling must come back |
| Free-shipping bar falls back to the default branch weight (1500 g) when a country has no free rate | bar hidden when no free band exists | the new branch has no such setting; the old bar promised free shipping that did not exist |
| `?country=XX` session override for QA | switcher and `?preview` only | not a shopper feature |
| Order status pages open to guests who know the id | signed-in owner only | privacy |
| Gateway pinged before the order is created | gateway must be connected; a provider error leaves a pending order with "Complete payment" | decision #68 / #78 |
| Cash on delivery hidden | same | client 2026-11-25 |
| Central products sold in selected countries only | all countries (decision #115) | client |
| Prices converted and stored per request currency | USD stored, other currencies display only (decision #87) | client |

Dev data note: there were no shipping rules at all before this round, so no cart showed a shipping charge. Rule "QA Gulf (dev test data)" (SA + EG, one band, $5.00) was added for testing.
