# Release QA — Admin panel (qa-admin)

> Final release test cycle, 2026-11-27. Owner: qa-admin. Method: browser first (Playwright, scripted), then Pest.
> Board ids: RQA-ADM-0xx. Severity: High / Medium / Low. Status: Open / Fixed (re-tested) / Not a bug / Env.

## Setup

- DB `nogrgr_qa` (tenant DB suffix `_qa`), migrated with `migrate`, `tenants:migrate`, `permissions:sync` (under `flock /tmp/nogrgr-qa-migrate.lock`, shared with qa-vendor).
- Server `php artisan serve --host=127.0.0.1 --port=8201 --no-reload`, `CACHE_STORE=redis`, `REDIS_PREFIX=qaadm_` / `CACHE_PREFIX=qaadm_` (own cache and queues), own worker `queue:work redis --queue=provisioning,default,notifications,mail --memory=1400`, log mailer, `TENANT_BASE_DOMAIN=localhost`.
- Accounts: `qa-admin-super@qa.test` (super-admin) and `qa-admin-limited@qa.test` (role `qa-admin-limited-role` = dashboard.view + catalog.products.*). Passwords only in the session scratchpad (never committed).
- Headless Chromium via Playwright 1.65 (`NODE_PATH=/var/www/delivery-plus/backend/node_modules`).

## Repeatable scripts — `backend/tests/e2e/admin/`

| Script | What it does |
|---|---|
| `lib.cjs` | login, 4 modes (1440 en/light, 1440 ar/dark, 375 ar/light, 375 en/dark), console / page / request error capture, DOM audit (raw keys, overflow, **clipped content**, placeholders, dead links, dir / dark), screenshots |
| `crawl.cjs` | every parameter-free admin page (`static-pages.txt`, 121) + one detail page per discovered URL pattern, in every mode |
| `modals.cjs` | every `[data-remote-modal]` opener on every list page opens; create modals submitted empty must give 422 + inline errors; every export link downloads |
| `flows.cjs` | end-to-end CRUD and actions through the real UI (see below) |
| `permissions.cjs` | limited role: sidebar shows only allowed links, every other page 403 (panel error page), write endpoints 403 |
| `dump.cjs` | dev helper: lists form controls / buttons of a page |
| `fixtures/` | test images (800×800 png) and a 2 s mp4 |

Run: `cd <scratch> && NODE_PATH=… QA_CREDS=<creds.json> QA_BASE=http://127.0.0.1:8201 node backend/tests/e2e/admin/flows.cjs [flow,…] [mode]`.

Flows in `flows.cjs`: products (create with image + video, 422, sale ≥ base rejected, edit, list search), variants (builder → 2 rows, sku/price/image, persisted), productDelete (+ export), categories, attributes, labels (icon picker), coupons, flashSales, fixedCosts, shippingRules, branches (intl-tel phone), companies (logo), stopPeriods, plans, postCategories, posts (rich editor, cover), pages, faqs, banners (image), templateParts, returnReasons, languages, currencies, promoCodes, roles, admins, vendors (create + provision a store, 8 profile tabs, limit override persisted, mobile app, commission, edit modal, suspend / reactivate, impersonate + end session), shipping (ORD-10904 to delivered via the status modal), maintenance (store window 3 days ahead, delete), cache (clear, “last cleared” updated), themes (13 listed, edit + countries modals, deactivate / activate, set default and back, home sections toggle persisted), emailTemplates (live preview, save, last edited, send test, reset, layout save), afterSales (refund reject 422 / reject / complete, return request-info, order refund 422s, order note), requests (thread message, payment request 422 / create, edit request detail), finance (payout 422s, create, list, cancel, settle 422, statement), settings (general save + restore, gateway limits save / persist / restore, platform / shipping / affiliate / delivery-popup forms), neozena (import 1 page), translations (add key 422 / save, edit, lock), compliance (verify document, download), reports (13 tabs + CSV export each, incl. AI usage), sorting (categories / products / labels keyboard reorder persisted), money (ORD-10003 amounts en + ar), shell (Ctrl+K search, bell, mark all read, 404, unknown record 404, 419, dark persists, ar = RTL).

Evidence: `docs/plan/release-qa/admin/` — `crawl_<mode>_<page>.jpg` (every page, 1440 en/light and 375 ar/light), `modal_*`, `flow_*`, `perm/*`.

## Findings

| Id | Sev | Status | Finding | Evidence | Fix (files) / regression test |
|---|---|---|---|---|---|
| RQA-ADM-001 | High | Fixed, re-tested | At 375 px (ar and en) content was cut off and unreachable on ~25 admin pages: product / category / attribute / branch edit forms (labels and inputs off-screen), order and shipping-queue detail, labels, badges, stop periods (calendar), integrations, docs, dashboard chart; status pill tabs (Products “Out of stock / Archived”, Affiliates payouts “Payout history”) could not be reached. Cause: grid items kept `min-width:auto`, so one wide child widened the track; the overflow was clipped. | `crawl_m-ar-light_admin__catalog__products__1__edit.jpg` (before), crawl3/crawl4 logs: 0 `clip` | `resources/css/panels/base.css` (zero-specificity `minmax(0,1fr)` / `min-width:0` / `canvas{max-width:100%}` inside `#main`), `components/ui/tabs.blade.php` (segmented tabs scroll), `components/ui/chart-card.blade.php`. Test `tests/Unit/Architecture/PanelGridOverflowTest.php` |
| RQA-ADM-002 | Medium | Fixed, re-tested | Role matrix (System › Roles create / edit) showed ~55 resources untranslated: in Arabic the rows were English (“Catalog Categories”, “Finance Ledger”, “Shipping Stop Periods”…), in English headline fallbacks. Dotted resource names were looked up as nested lang keys; 5 resources had no label. | `crawl_m-ar-light_admin__system__roles__3__edit.jpg` | `Identity/Support/PermissionMatrix.php`, `Identity/resources/lang/{en,ar}/roles.php` (+ admin module groups sales / shipping / marketing / storefront). Test `tests/Feature/Identity/PermissionMatrixLabelsTest.php` (both guards, en + ar) |
| RQA-ADM-003 | Low | Fixed, re-tested | 31 `data-ajax` forms had no `novalidate`: the browser showed its own English bubble (“Please select an item in the list.”, also in Arabic UI) and the request never reached the server (promo code form, return-reason modal, payouts, order cancel / company, shipping status, …). | `flow_promoCodes_*` | `novalidate` on all 31 forms. Test `tests/Unit/Architecture/AjaxFormsNovalidateTest.php` |
| RQA-ADM-004 | — | Not a bug | Limited role gets 403 on `/admin/notifications`: the inbox is gated by the grantable `notifications.view` permission and the bell is hidden without it — consistent. | `permissions.cjs` | — |
| RQA-ADM-005 | High | Fixed, re-tested | Every templated e-mail’s call-to-action button had an invisible label (brand-coloured text on the brand-coloured button): the generic link rule added a second `style` attribute to the button anchor. Seen in the admin template preview; same renderer sends the real mails (central + store, en + ar). | `crawl_d-en-light_admin__settings__email-templates__66__edit.jpg` | `Notifications/Support/MailBodyStyler.php` (single style attr, `onColor()` white or ink for light brands). Test `tests/Feature/Notifications/MailButtonContrastTest.php`. Re-checked in the preview iframe: label `rgb(255,255,255)` on `#ff5a2c` |
| RQA-ADM-006 | High | Fixed (Pest) | Queued `StoreSupportNotification` / `StoreRequestNotification` failed in the worker (`Cannot initialize readonly property …SupportTicketNotification::$eventName from scope …StoreSupportNotification`) — stores never got support / request notifications (failed_jobs on nogrgr_qa). | failed_jobs id 3 | `Support/Notifications/SupportTicketNotification.php`, `Catalog/Notifications/RequestUpdateNotification.php` (no readonly on the serialized parents). Tests `tests/Feature/Support/QueuedSupportNotificationSerializationTest.php`, `tests/Unit/Architecture/QueuedReadonlyPropertiesTest.php`. After the fix the worker processed RequestUpdateNotification jobs without failures |
| RQA-ADM-007 | Low | Fixed, re-tested | Translations › “Translate with AI” with nothing missing: “Every key already has a Arabic translation.” and the submit stayed enabled (422 without visible message). | `modal_d-en-light___admin__localization__ai-translation__static-keys__create_file_storefront_locale_ar.jpg` | `Ai/resources/lang/{en,ar}/admin_translation.php`, `static-keys-form.blade.php` (`data-submit-disabled`), `resources/js/panels/remote-modal.js`. Test `tests/Feature/Ai/AdminAiTranslationModalStateTest.php` |
| RQA-ADM-008 | High | Fixed (Pest) | Found by fixer 2: `BackInStockNotification` redeclared `Notification::$locale` as readonly → fatal error whenever the class loads (back-in-stock mails never sent). | — | `Inventory/Notifications/BackInStockNotification.php` (`$mailLocale`). Covered by `QueuedSupportNotificationSerializationTest` |
| RQA-ADM-009 | Low | Fixed, re-tested | Product sort drag handles read “Drag  (arrow keys move it)” without the product name (banners: “Drag to sort” for every row). | `dump.cjs /admin/catalog/products/sort` | `ProductFormService::sortPage` `handle_label`, `panels/admin/products.js`, `products/sort.blade.php`, banner `_card-cell` (admin + vendor), `Engagement/lang/{en,ar}/banners.php`. Tests `ProductSortTest`, `BannerTest` (RQA-ADM-009 cases) |
| RQA-ADM-010 | Low | Fixed | Stale test: `EmailTemplateSystemTest` › disabled template used the now-mandatory `AdminPasswordChanged` template and failed. | — | Test rewritten with `AdminRoleChangedNotification` + new case “always mails a mandatory template even when disabled” |
| RQA-ADM-011 | Low | Fixed, re-tested | Inline 422 errors stayed under a field after the user corrected it (every panel form except products). | `flow_labels_create_filled.jpg` (m-ar before) | `resources/js/core/form-errors.js` (`bindClearOnInput`, `clearFieldError`), `core/index.js`. Test `tests/Unit/Architecture/FormErrorsClearOnInputTest.php` |

### Environment-only (not product defects)

- ENV-1: every AI call on nogrgr_qa fails (OpenAI 429 rate limit / quota, FreeLLMAPI “rejected the API key”). AI translation output, AI usage with real tokens and other AI tools could not be verified end to end; keys must be supplied by the client (not entered by QA).
- ENV-2: `queue:work` default `--memory=128` stops the worker after `StartCatalogImport` (“Memory limit exceeded”); production uses Horizon (`config/horizon.php` supervisor, queues incl. `provisioning`). QA worker runs with `--memory=1400`.
- ENV-3: the e-mail layout preview iframe (sandboxed) logs “Blocked script execution” because Laravel Boost injects its browser logger into every local HTML response. Not present in production.
- ENV-4: one transient 500 “Vite manifest not found” while another agent ran `npm run build`.

## Results per module (browser)

Legend: PASS = crawl (4 modes: no JS errors, no failed requests, no raw keys, no overflow / clipping, RTL + dark applied) + listed flows green.

| Module | Result | Notes |
|---|---|---|
| Dashboard | PASS | KPIs, chart fits after 001, report export |
| Catalog — products (CRUD, media image + mp4 video, variants builder, sort, export, delete) | PASS | sale ≥ base → 422 |
| Catalog — categories, attributes, labels (icon picker), badges, sort | PASS | |
| Catalog — Neozena import (1 page) | PASS | run 1 completed: 15 items, 0 created (existing, update off) |
| Requests center (products / edits / manufacturing / brands / tenant changes) | PASS | thread message, payment request, archive control; no pending tenant changes in data |
| Orders / shipping queue / returns / refunds | PASS | ORD-10904 → delivered for qa-app; refund 422s, reject / complete, return request-info, order notes |
| Promotions — coupons, flash sales | PASS | |
| Logistics — shipping rules, fixed costs, branches, companies, stop periods, settings, delivery popup | PASS | |
| Vendors — list, create + provision, 8 profile tabs, limit overrides, mobile app, compliance, domains, impersonation, suspend / reactivate, export | PASS | pending registrations / domain requests: empty-state only (no data) |
| Plans & subscriptions | PASS | plan CRUD; subscriptions export |
| Finance — ledger, settlements, payouts, transactions, invoices, payment logs, statement | PASS | payout create / cancel; settle modal 422 |
| Affiliates — accounts, conversions, payout requests, promo codes, settings | PASS | promo-code CRUD; payout requests: empty-state only (no requests in data) |
| Reports (13 tabs incl. AI usage) | PASS | every CSV export 200 |
| Content — blog posts / categories, pages, FAQs, newsletter | PASS | rich editor, cover upload |
| Storefront — banners, themes (13, default, activate, countries), home sections, template parts, return policy, theme review queue | PASS | review queue: empty-state only |
| Localization — languages, translations (+ AI modal), key locks, currencies, countries, purchases | PASS (AI = ENV-1) | |
| Settings & integrations — general, platform, gateways, gateway limits, email layout, email templates (preview / test / reset) | PASS after 005 | |
| System — admins, roles matrix + limited-role enforcement, audit log (+ export), maintenance windows, cache tools, docs, UI kit | PASS after 002 | limited role: 0 unexpected pages, 4/4 write endpoints 403 |
| Support tickets | PASS (list / filters / empty state) | no tickets in QA data to reply to; reply path covered by Pest + 006 |
| Notifications bell / inbox | PASS | mark all read, badge cleared |
| Error pages / global search / theme + language | PASS | 404 / 419 / 403 in panel layout, Ctrl+K |
| Money (ORDER_FORMULAS) | PASS | ORD-10003 hand check 243.70 = 195.93 + 47.77 (flash 29.16, coupon 26.25, shipping 7.50) shown in en + ar; invariants `platform + vendor = grand_total`, `price = cost + profit`, line totals hold for every stored order in all 10 stores |
