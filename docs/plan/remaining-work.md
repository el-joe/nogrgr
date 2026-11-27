# Remaining Work: What Is Left to Finish

> Owner: QA (coverage-truth) · Date: 2026-11-27 · Source: [05-page-coverage.md](05-page-coverage.md), checked against the code on `main` (route list, views, Pest tests, notifications).
> A row is only `done` when its route + view + test exist (mails: notification sent by a real event, en / ar texts, test). This audit moved 107 stale rows to `done`. **57 rows remain.** They are grouped below into **33 work items**.
> Size: **S** ≤ 1 day · **M** 2–4 days · **L** 1–2 weeks (one developer, tests included).

## Summary

| Phase | Rows left | Work items | Main blocker |
|---|---|---|---|
| P2 Foundation (panels, auth, billing mails) | 22 | 11 | none (designs exist) |
| P3 Catalog | 0 | — | — |
| P4 Shipping | 0 | — | — |
| P5 Orders, payments, finance mails | 13 | 9 | 3 product decisions (see D1–D3) |
| P6 Central website | 0 | — | — |
| P7 Themes, storefront engine | 8 | 5 | theme admin + page builder |
| P8 Vendor polish | 4 | 4 | public API scope (D4) |
| P10 AI | 2 | 2 | — |
| P11 Mobile | 1 | 1 | — |
| **Total** | **57** | **33** | |

## Decisions the client / PM must take first

| # | Question | Rows | Proposal |
|---|---|---|---|
| D1 | The old system had separate "Order processing" and "Payment receipt" e-mails. The new one sends one "Order confirmation" when the order is paid (the order goes to processing at that moment). | MAIL-013, MAIL-017 | Merge both into MAIL-012 and mark them `dropped`. Otherwise **S** each. |
| D2 | The wallet was replaced by the double-entry ledger (01-modules). There is no wallet transaction to send a receipt for. | MAIL-025, MAIL-026 | Drop them, or send them on manual ledger adjustments (**S**). |
| D3 | An attached PDF invoice needs a PDF engine (none is installed yet; invoices are printable HTML). | MAIL-020, MAIL-047 | Choose a PDF engine (e.g. dompdf or headless Chrome). Record it in decisions.md. |
| D4 | Scope of the public API v1 for stores (which resources, rate limits, key scopes). | VEN-085 | Agree on the resource list before building. |
| D5 | Is the internal vendor UI kit needed? The admin UI kit (`admin.ui-kit`) already shows every shared component. | VEN-093 | **Decided: keep** (internal-only, decision #116). |

## P2 Foundation

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 1 | Admin panel error pages 403 / 404 / 419 / 500 / 503 in the panel layout | ADM-120…124 | S | — | Today `/admin/*` shows the central-website error pages (resources/views/errors/*). Add an admin branch like the affiliate one. |
| 2 | Vendor panel error pages 403 / 404 / 419 / 500 | VEN-096…099 | S | — | Fixes QA-FINAL-010: today an unknown vendor record shows the **storefront** 404. Figma 82:102128…82:102194. |
| 3 | Subscription expired / store suspended screen in the vendor panel (renew CTA) | VEN-095 | S | billing state (exists) | Figma 82:101634. The storefront already has the suspended page. |
| 4 | Shared "plan limit reached / upgrade" modal | VEN-094 | S | — | Some features already show their own limit state (AI tools `_limit`, staff, banners). Add one shared modal and use it everywhere (Figma 82:102216). |
| 5 | Vendor UI kit | VEN-093 | S | D5 | Keep — internal-only page (#116). |
| 6 | Maintenance windows (schedule, message per language, central + per store) | ADM-113 | M | — | No route yet. |
| 7 | Cache tools: main cache + tenants cache tabs | ADM-114, 114a, 114b | S | — | No route yet. Each action must be audited. |
| 8 | Shared branded e-mail layout (central brand / store brand, en / ar RTL, plain-text alternative) | MAIL-001 | M | — | Today: admin templates use a basic layout. Support and affiliate mails have their own built-in layouts. Every other mail uses the stock Laravel `MailMessage` layout. Figma 129:10149. Every MAIL row depends on this item to match the design. |
| 9 | Vendor welcome mail: add a test | MAIL-003 | S | — | VendorWelcomeNotification is already sent on TenantProvisioned. Only a test asserting the send is missing. |
| 10 | Renewal reminder mails (store owner + admins) | MAIL-007, MAIL-009 | M | scheduler | Needs a daily command that finds subscriptions N days before renewal, plus the two templates. |
| 11 | Admin mail: subscription cancelled; store mail: domain connected / DNS failed | MAIL-010, MAIL-032 | S | — | The `SubscriptionCancelled` event exists but has no listener. The DNS check job does not notify yet. |

## P5 Orders, payments, finance

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 12 | Gateway limits tab (notify / block thresholds per gateway) + the two admin mails | ADM-100c, MAIL-023, MAIL-024 | M | payment logs (exist) | Needs a threshold model, a volume check on capture, and blocking of the gateway at checkout. Figma 79:86634. |
| 13 | Admin shipping-escalation mail | MAIL-022 | S–M | shipping queue (exists) | Define the rule first (e.g. paid order not shipped within the max delivery days). |
| 14 | Admin mail when a store settles its balance itself | MAIL-028 | S | — | `vendor.finance.settle.pay` has no admin notification. Manual settlements already notify the store (MAIL-027). |
| 15 | Vendor order receipt (print) + test | MAIL-044 | S | — | The admin receipt is done. The vendor side only has `vendor.sales.orders.invoice`, with no test. |
| 16 | Shipping label (A6 / A4, barcode): admin GET test + vendor route | MAIL-045 | S | — | The admin view `commerce::admin.shipping.label` exists but has no test. There is no vendor route. |
| 17 | Payout / settlement receipt PDF | MAIL-047 | S–M | D3 | |
| 18 | Order invoice attached to the customer mail as a PDF | MAIL-020 | S | D3 | |
| 19 | Processing / payment receipt mails | MAIL-013, MAIL-017 | S | D1 | |
| 20 | Wallet transaction mails | MAIL-025, MAIL-026 | S | D2 | |

## P7 Themes and storefront engine

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 21 | Admin › Storefront › Themes (list of 13, edit metadata / preview image / countries) | ADM-086, ADM-086a | M | — | No admin route yet. Stores already pick themes (VEN-030). |
| 22 | Admin home variants and sections config + template parts CRUD | ADM-087, ADM-088, ADM-088a | M | 21 | The vendor side (VEN-031) reads the variant config. |
| 23 | Vendor page builder (section order / visibility, preview) | VEN-032 | M–L | 22 | Figma 200:33486. |
| 24 | ~~Custom Twig themes~~ **done** (decision #121): vendor upload / validation report / versions / preview / submit / publish / roll back / on-off, starter kit, settings form from theme.json; admin review queue + detail with diff; reviewed / submitted / render-failed notifications; sandboxed render engine with CSP and fallback; docs (docs/themes/README.md + in-panel guide) | VEN-036, 036a, 036b, ADM-089, 089a, MAIL-034, SF-ALL-006 | — | — | Done 2026-11-27 (tests/Feature/Storefront/CustomThemes). Left for later: tracking pixels on custom theme pages (need CSP allowances), custom-theme entries in the 13-theme catalogue UI (#119, admin-themes uses `CustomThemeRepositoryContract::approvedForTenant`). |
| 25 | Central theme preview `/preview/{slug}` (country switch, exit) | WEB-004 | M | demo store data | Fix QA-FINAL-007 (demo banner category links) at the same time. Figma 127:11426 / 127:11559. |
| 26 | Customer welcome mail on storefront sign-up | MAIL-042 | S | 8 | |

## P8 Vendor polish

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 27 | Vendor dashboard KPIs, period selector, latest orders / top customers / top products | VEN-009 | M | Reporting facts (exist) | Widgets 009a–f are done. First check Figma 12:11267: if the client frame has no KPI block, change the row scope and mark it done. |
| 28 | Product share modal (copy, share links) | VEN-010d | S | — | |
| 29 | Settings › API keys (create, revoke, copy once) for the public API v1 | VEN-085 | M–L | D4 | |
| 30 | Admin integrations › Tracking defaults tab | ADM-100k | S | — | Stores already save their own pixels (VendorTrackingPixelsTest). |

## P10 AI

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 31 | Admin products: image-search modal | ADM-010a | S | image search service (exists) | Reuse ImageSearch on the central catalog index. Figma 65:29682. |
| 32 | Vendor profile › Limit overrides tab (AI logo, image search, AI calls, video ads per store) | ADM-041h | M | AiQuotaService, plan limits | |

## P11 Mobile

| # | Work item | Rows | Size | Depends on | Notes |
|---|---|---|---|---|---|
| 33 | Storefront app landing page ("Open in app / Install") | SF-ALL-005 | S–M | VEN-037 store links (exist) | |

## Open defects outside the page rows (qa-log, Final QA gate)

QA-FINAL-007 (demo banner links), 008 (absolute notification URLs), 009 (currency rates never synced on QA), 010 (vendor 404, item 2 above), 011 (audit-log event names). All are Low.

## Estimate

About **18 S + 3 S–M + 9 M + 2 M–L + 1 L items**. That is roughly 7–9 developer-weeks for one developer, or 4–5 weeks with two in parallel. Items 21–24 (themes) and 10–12 (scheduler / gateway limits) are the longest chains.
