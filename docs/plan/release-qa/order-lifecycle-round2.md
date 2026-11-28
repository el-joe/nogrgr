# Order lifecycle + account parity, round 2 (be-qa, 2026-11-28)

Store `vendor.nogrgr.loc` (Ecommet, tenant 1, dev DB). Headless Chromium (Playwright). Payments: the store's Stripe row in test mode (inline card); COD is not offered at checkout (client decision) and PayPal is not configured for this store. Test accounts: shopper `qa-be-shopper@example.test`, vendor staff `qa-be-order@example.test`, admin `qa-be-admin@example.test` (passwords only in the session scratchpad). Checkout conditions and numbers: `checkout-parity.md`.

## 1. Lifecycle matrix

| Step | Result |
|---|---|
| Register, add simple + variant product, change quantity, remove with undo, wrong coupon, valid coupon | PASS (2.57 items, -0.14 flash, -0.24 coupon, 2.19) |
| Address modal (empty-form errors, intl phone, country combobox, city), shipping quote | PASS |
| Declined card (4000 0000 0000 0002) then "Complete payment" retry | PASS (ORD-10001, message under the card, order stays pending) |
| Successful payment, success page | PASS, total 8.21 |
| Admin: processing, packed, in transit (tracking no.), out for delivery, delayed, delivered | PASS; every step in history, timeline, notification and e-mail; branch stock -3 on dispatch |
| Review, reorder, invoice | PASS |
| Return request (method, note, photo + video, estimate) | PASS after fixes (D-05, D-06, D-07) |
| Thread: store asks for info, customer reply (short message refused) with photo, approve, received, inspect + restock + refund | PASS (RF-5001 2.14 completed, stock +2) |
| Second return, withdrawn by customer | PASS |
| Customer cancel, 2-step modal ("Other" needs a note), 375 ar | PASS after D-02; full refund 9.76, hold released, ledger reversal |
| Admin cancel (reason + confirm) | PASS; refund 6.19, ledger reversal |
| Partial refund 2.00 then the rest 4.19 | PASS; payment "Partially refunded" then "Refunded"; platform share unchanged on the partial one (ORDER_FORMULAS §3) |
| Ledger (store's own gateway: store owes the platform share) | PASS: 7.85 debit per paid order, reversed on cancel / full refund |
| Notifications / e-mails (customer, store, admin; log mailer) | PASS |
| `completed` status | Not reachable from any screen (open, O-1) |

## 2. Order details and tracking: old vs new

| Field / action (old order-status, order-tracking, profile list) | Before | Now |
|---|---|---|
| Order number, placed date, status badge | present | present |
| Cancelled banner: reason, date, by whom, refund message | missing | fixed |
| Refunds: reference, amount, method, status, requested / completed, rejection note | missing | fixed |
| "Partially refunded" payment status | missing | fixed |
| Payment method + payment status card | partial | fixed |
| Items: image, name, variant | present | present |
| Unit price x qty, line discount | missing | fixed |
| Per item: return state link, "Request return (n left)" | missing | fixed |
| Totals: subtotal incl. fixed shipping, discount, tax, shipping "Free", coupon, total | "Free" missing | fixed |
| Shipping and billing address | present | present |
| Carrier, expected delivery (always shown, N/A) | only with a tracking no. | fixed |
| Tracking number + carrier link | present ("· AWB" without carrier) | fixed |
| Timeline with upcoming "Expected" steps | past only | fixed |
| Order activity log | missing | fixed |
| Need help / contact support | missing | fixed |
| Shipped notice "return after delivery" | missing | fixed |
| Cancel (before shipping), reorder, review, invoice, track | present | present |
| Guest access by order id | old allowed | signed-in owner only (kept) |
| Orders list: info banner, item count | missing | fixed |

## 3. Account pages: old vs new

| Page | Old content / action | Status |
|---|---|---|
| Profile > orders | status chips, list, view, reorder, cancel, review | present (+ search, Refunded tab) |
| Profile > details | name, e-mail, phone | present (+ edit, language, password, delete) |
| Profile > saved addresses (add, edit, default, delete) | on the profile page | fixed (same address book component; the Addresses page stays) |
| Profile > sign out | button | present |
| Returns list | order, reason, date, status, refund, last note, view details | present |
| Returns list | "View order" | fixed |
| Return detail | status, steps, thread, reply with files, withdraw confirm, evidence | present |
| Return detail | request date + order link, step dates, method, replacement, note, inspection, refunds, "more info requested", replacement ship date | fixed |
| Return form | quantity, reason, description, return / exchange, photos, video, policy | present |
| Return form | return method, replacement option (same price, in stock), notes, estimated refund, `?item=` preselect | fixed |
| Address modal | all canonical fields | present |

## 4. Defects

| ID | Sev | Defect | Fix |
|---|---|---|---|
| D-01 | High | Hair accessories (products 1-34) all "Out of stock" | Dev data from the first catalog import before default branches were auto-created (log: "stock not saved: no active branch"); not caused by testing. Restocked with 100 per variant. Real bug also fixed: such an import kept its checksum, so later syncs skipped it as "unchanged" for ever |
| D-02 | High | Cancel modal sent the translated label as free text: no reason code stored, "Other" needed no note | sends `reason_code` + note |
| D-03 | High | A paid order fully refunded before shipping kept its stock hold; own-product stock was never returned on cancel | release on cancel / pre-shipment refund; restock on refund only for shipped units |
| D-04 | High | Checkout country chain differed from the old project; drawer and cart page showed different totals | see `checkout-parity.md` |
| D-05 | High | Return submit with files: 500 (`central` disk folders 0700, other user) | group-writable disk permissions |
| D-06 | High | A failed upload left a return without evidence while the customer saw an error | create + evidence in one transaction |
| D-07 | High | Return form fields of the old project missing | return method, notes, exchange option, estimate |
| D-08 | Medium | "Add a shipping address" stayed after saving one | hidden on selection |
| D-09 | Medium | Account / order content missing (tables 2 and 3) | added with existing components |
| D-10 | Medium | Plan "orders per month" never enforced | checkout block |

## 5. Open

- O-1 `completed`: the old admin could pick any status; the new screens end at `delivered`. Needs a decision (manual action, or auto after the return window).
- O-2 Items in `checkout-parity.md` §3.
- O-3 The queue worker running on this machine was started before this round; restart it so queued jobs use the new code.
- Figma parity not checked (Figma not used in this round).

## 6. Remaining (resume here after a reboot)

- Verified: `tests/Feature/Commerce` 108/108 and `tests/Unit` 441/441 passed on this code. Browser lifecycle and account pages (1440 / 375, en / ar) done.
- Not yet confirmed: the second targeted run (account, cart, checkout, returns, catalog pages, API, import tests, including `AccountParityTest` and `CheckoutCountryParityTest`). Re-run:
  `flock /tmp/nogrgr-tests.lock php artisan test --parallel --processes=12 --colors=never --filter="AccountParity|CheckoutCountryParity|AccountOrdersPages|StorefrontAccountPages|CartPages|CheckoutPages|StorefrontThemeContext|CatalogPages|ProductImport|StoreApiReturns|AdminReturnsRefunds|FullRefundPayment|RefundWebhooks|PaymentReturn|StoreApiCatalog|StoreApiLocale|MobileApi|InnerPages|DemoStore|VendorSales|AdminOrders"`
  Then fix failures; the full suite is the orchestrator's run.
- After pulling: `php artisan migrate` (return_requests columns, already applied on the dev DB) and `npm run build`.
- Open items O-1 to O-3 above and `checkout-parity.md` §3.
