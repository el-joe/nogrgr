# Page Coverage — Gate Checklist

> Owner: PM · Date: 2026-11-24 · Rule (client, decisions.md #24): **no page is skipped and nothing ships as "coming soon"**. A phase gate closes only when every row of that phase is `done`.
> Sources: `old-system-docs/routes-inventory.md` (GET routes that render a page/modal; POST/PUT/DELETE/JSON = actions on their page), `dashboard-*.md`, `storefront.md`, `module-inventory.md`, [01-modules.md](01-modules.md) (§D mapping), [03-figma-inventory.md](03-figma-inventory.md), `figma-metadata/*.xml`.
> **Std** = loading + empty + error states + 403 for missing permission (every list is a server-side DataTable with search, filters, pagination). `m` = mobile frame. Figma "design needed" = UI/UX designs it in P1 before FE starts (from the surface's design language; "reuse X" names the base frame). Sub-rows (`a`, `b`…) are modals, tabs or states of the parent page.
> Status values: `todo` → `in-progress` → `done`; `dropped` (reason from 01-modules.md); `post-launch` (client-approved deferral).

## 1. Admin panel (`{central}/admin`)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| ADM-001 | Login | `admin.auth.login` | Std; validate, remember, throttle, inactive-account error | admin.login | designed: Admin Panel 55:20387 | P2 | todo |
| ADM-002 | Forgot password | `admin.auth.password.request` | Std; send link, sent state | — (new) | designed: Admin Panel 81:97508 | P2 | todo |
| ADM-003 | Reset password | `admin.auth.password.reset` | Std; invalid/expired token | — (new) | designed: Admin Panel 81:97545 | P2 | todo |
| ADM-004 | Panel shell (sidebar, top bar, breadcrumb, bell dropdown, dark/light, locale) | layout `x-admin::layout` | permission-filtered nav, live bell (Reverb), unread count | AdminNavigation, admin-notification-bell | designed: Admin Panel 41:12632 · Sidebar 37:12436 · Topbar 35:11474 | P2 | todo |
| ADM-005 | My profile & password | `admin.account.edit` | Std; edit, change password | — (new) | designed: Admin Panel 80:97736 | P2 | done |
| ADM-006 | Dashboard | `admin.dashboard` | Std; KPI cards, revenue chart, latest payments, period filter | admin.dashboard | 41:12632, m 43:13511, RTL 56:17016, dark 57:17198 | P9 | partial — shell + layout built in P0 (live store counts, real config health, 0-valued commerce metrics). Hidden until data + route exist (no dead buttons): "All countries" filter, "Export report", needs-attention chevrons/links, "View all" / "Leaderboard" / "System tools" card links |
| ADM-007 | Notifications inbox | `admin.notifications.index` | Std; filter type, open, mark read / all read, delete | admin.notifications.index, admin.notifications.latest, admin.notifications.open | designed: Admin Panel 80:96878 | P2 | done |
| ADM-007a | ↳ Notification preferences tab | `admin.notifications.preferences` | Std; per-channel toggles | — (new, A6) | designed: Admin Panel 80:96878 | P2 | done |
| ADM-010 | Products list | `admin.catalog.products.index` | Std; list, filter (status/category/scope/stock/trashed), delete, restore, export | admin.products.index | designed: Admin Panel 44:13636 · m 45:14601 · filters sheet 45:14748 | P3 | done |
| ADM-010a | ↳ Image search modal | `admin.catalog.products.image-search` | upload, results, no-match, quota error | ProductsList image search | designed: Admin Panel 65:29682 | P10 | todo |
| ADM-010b | ↳ Price list modal (per-country price) | modal | per-country/variant prices | ProductsList price list | designed: Admin Panel 65:29682 | P3 | done |
| ADM-011 | Product create / edit | `admin.catalog.products.create` / `.edit` | Std; create, edit, validate-on-blur, unsaved-changes guard | admin.products.create, .edit | designed: Admin Panel 46:14760 · 47:15543 | P3 | done |
| ADM-011a | ↳ Tab: General & translations (locale tabs) | tab | per-locale fields | AddEditProduct | designed: Admin Panel 47:15543 | P3 | done |
| ADM-011b | ↳ Tab: Pricing & weight (cost/sale, grams) | tab | money validation | AddEditProduct | designed: Admin Panel 65:28688 | P3 | done |
| ADM-011c | ↳ Tab: Variants (attribute options, per-variant image/price) | tab | build/remove variants | AddEditProduct | designed: Admin Panel 46:14760 | P3 | done |
| ADM-011d | ↳ Tab: Media gallery | tab | upload, reorder, remove, AVIF/embedding job state | AddEditProduct | designed: Admin Panel 65:28688 | P3 | done |
| ADM-011e | ↳ Tab: Countries & shipping (`allowed_country_ids` NULL/[]/list) | tab | all/none/selected | AddEditProduct | designed: Admin Panel 65:28688 | P3 | done |
| ADM-011f | ↳ Tab: Categories, labels, badges | tab | multi-select | AddEditProduct | designed: Admin Panel 65:28688 | P3 | done |
| ADM-011g | ↳ Tab: Tenant assignment (all / selected) | tab | tenant picker | AddEditProduct | designed: Admin Panel 65:28688 | P3 | done |
| ADM-011h | ↳ Tab: Stock per branch | tab | per-branch/variant qty | AddEditProduct, branch_products | designed: Admin Panel 65:28688 | P3 | done |
| ADM-012 | Product sort | `admin.catalog.products.sort` | drag & drop, save | admin.products.sort | designed: Admin Panel 65:21308 | P3 | done |
| ADM-013 | Product import (external catalog, Neozena driver) | `admin.catalog.imports.index` | run import, progress, log, errors | /prod (DevTools@importNeozenaProducts) | designed: Admin Panel 65:22120 | P3 | done |
| ADM-014 | Categories list (tree) | `admin.catalog.categories.index` | Std; list, delete, toggle featured | admin.categories.index | designed: Admin Panel 65:22996 | P3 | done |
| ADM-014a | ↳ Category create / edit | `admin.catalog.categories.create` / `.edit` | translations, thumbnail, parent | admin.categories.create, admin.categories.edit | designed: Admin Panel 65:23866 | P3 | done |
| ADM-014b | ↳ Category sort | `admin.catalog.categories.sort` | drag & drop | admin.categories.sort | designed: Admin Panel 65:24654 | P3 | done |
| ADM-014c | ↳ Products order inside category | `admin.catalog.categories.products` | drag & drop | admin.categories.products | designed: Admin Panel 65:24654 | P3 | done |
| ADM-015 | Attributes (variations) list | `admin.catalog.attributes.index` | Std; list, delete | admin.variations.index | designed: Admin Panel 65:25447 | P3 | done |
| ADM-015a | ↳ Attribute create / edit (options) | `admin.catalog.attributes.create` / `.edit` | translations, options repeater | admin.variations.create, admin.variations.edit | designed: Admin Panel 65:26338 | P3 | done |
| ADM-016 | Product labels list | `admin.catalog.labels.index` | Std; list, toggle active, delete, sort | admin.labels.index | designed: Admin Panel 65:27123 | P3 | done |
| ADM-016a | ↳ Label create / edit (icon, placeholder text e.g. `{delivery_date}`) | `admin.catalog.labels.create` / `.edit` | placeholder preview | admin.labels.create, admin.labels.edit | designed: Admin Panel 65:29682 | P3 | done |
| ADM-017 | Badges hub (tabs per badge) | `admin.catalog.badges.index` | Std; list per badge | admin.badges.show | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017a | ↳ Tab: New in | `admin.catalog.badges.show` | assign, remove | admin.badges.new-in | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017b | ↳ Tab: Best selling | `admin.catalog.badges.show` | assign, remove | admin.badges.best-selling | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017c | ↳ Tab: Featured | `admin.catalog.badges.show` | assign, remove | admin.badges.featured | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017d | ↳ Tab: Recommended | `admin.catalog.badges.show` | assign, remove | admin.badges.recommended | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017e | ↳ Tab: Trending now | `admin.catalog.badges.show` | assign, remove | admin.badges.trending-now | designed: Admin Panel 65:27887 | P3 | done |
| ADM-017f | ↳ Assign products modal (search / whole category) | modal | search, bulk add | admin.badges.search | designed: Admin Panel 65:29682 | P3 | done |
| ADM-017g | ↳ Badge sort | `admin.catalog.badges.sort` | drag & drop | admin.badges.sort | designed: Admin Panel 65:27887 | P3 | done |
| ADM-018 | Requests center (hub) | `admin.requests.index` | Std; unread counts per tab | — (new, §D) | designed: Admin Panel 68:28760 | P3 | done |
| ADM-018a | ↳ Product edit requests list | `admin.requests.edits.index` | Std; filter status | admin.products.edit-requests | designed: Admin Panel 68:29747 | P3 | done |
| ADM-018b | ↳ Edit request detail (diff, approve → tenant override, reject) | `admin.requests.edits.show` | approve, reject (reason) | admin.products.edit-requests.show | designed: Admin Panel 68:30673 | P3 | done |
| ADM-018c | ↳ Product requests list | `admin.requests.products.index` | Std; filter status, archive | admin.product-requests.index | designed: Admin Panel 68:28760 | P3 | done |
| ADM-018d | ↳ Product request detail (chat, files, status, priority) | `admin.requests.products.show` | reply, upload, live refresh, file download | admin.product-requests.show, admin.product-requests.file | designed: Admin Panel 68:31430 | P3 | done |
| ADM-018e | ↳ Manufacturing requests list | `admin.requests.manufacturing.index` | Std; filter status/tenant, status modal | admin.manufacturing.index | designed: Admin Panel 68:32218 | P3 | done |
| ADM-018f | ↳ Manufacturing detail (chat, notes, payment requests modal) | `admin.requests.manufacturing.show` | reply, status, create/cancel payment request, file | admin.manufacturing.show, admin.manufacturing.file | designed: Admin Panel 68:33125 | P3 | done |
| ADM-018g | ↳ Brand requests list | `admin.requests.brands.index` | Std; filter, status modal | admin.brand-requests.index | designed: Admin Panel 68:33923 | P3 | done |
| ADM-018h | ↳ Brand request detail (chat, payment requests) | `admin.requests.brands.show` | reply, status, payment request | admin.brand-requests.show | designed: Admin Panel 68:33923 | P3 | done |
| ADM-018i | ↳ Tenant change requests (countries / categories) | `admin.requests.tenant-changes.index` | approve, reject (note) | admin.plans.tenant-change-requests | designed: Admin Panel 68:34678 | P3 | done |
| ADM-020 | Orders list (all tenants, from facts) | `admin.orders.index` | Std; filter status/payment/gateway/tenant, export | admin.orders.index | designed: Admin Panel 48:16236 | P5 | done |
| ADM-021 | Order detail | `admin.orders.show` | Std; timeline, tracking no., own-product orders read-only | admin.orders.show | designed: Admin Panel 49:17280 | P5 | done |
| ADM-021a | ↳ Cancel order modal (2-step, reason codes) | modal | reason, note if "other" | OrderDetailPage cancel | designed: Admin Panel 70:41386 | P5 | done |
| ADM-021b | ↳ Refund modal (≤ refundable) | modal | amount, reason | OrderDetailPage refund | designed: Admin Panel 70:41386 | P5 | done |
| ADM-021c | ↳ Create return modal | modal | reason ≥ 10 chars | OrderDetailPage return | designed: Admin Panel 70:41386 | P5 | done |
| ADM-021d | ↳ Reassign shipping company modal | modal | company picker, new estimate | — (new, §D resolved) | designed: Admin Panel 70:41386 | P5 | done |
| ADM-022 | Shipping list (fulfilment queue) | `admin.shipping-queue.index` | Std; filter shipping status | admin.shipping.in-delivery | designed: Admin Panel 70:34839 | P5 | done |
| ADM-022a | ↳ Shipping order detail (status modal, tracking, attachments, create return) | `admin.shipping-queue.show` | upload/delete attachment | admin.shipping.in-delivery.show | designed: Admin Panel 70:35864 | P5 | done |
| ADM-023 | Return requests list | `admin.returns.index` | Std; filter status/tenant | admin.orders.returns.index | designed: Admin Panel 70:36668 | P5 | done |
| ADM-023a | ↳ Return detail (approve, reject, more info, inspect + restock, refund, ship exchange, notes) | `admin.returns.show` | one modal per transition | admin.orders.returns.show | designed: Admin Panel 70:37613 · 70:41386 | P5 | done |
| ADM-024 | Refunds list | `admin.refunds.index` | Std; filter tenant/status/source/date; retry, complete, reject modals | admin.orders.refunds.index | designed: Admin Panel 70:38484 · 70:41386 | P5 | done |
| ADM-025 | Coupons (central) | `admin.promotions.coupons.index` | Std; list per country, delete | admin.store.coupons.index, admin.store.coupons.list | designed: Admin Panel 70:39532 | P5 | done |
| ADM-025a | ↳ Coupon create / edit modal | `admin.promotions.coupons.create / .edit` | type, value, min spend, dates, countries | CouponsPage | designed: Admin Panel 70:41386 | P5 | done |
| ADM-026 | Flash sales (central) | `admin.promotions.flash-sales.index` | Std; list per country, delete | admin.store.flash-sales.index, admin.store.flash-sales.list | designed: Admin Panel 70:40462 | P5 | done |
| ADM-026a | ↳ Flash sale create / edit (product picker) | `admin.promotions.flash-sales.create / .edit` | search products, %, dates, countries | FlashSalesPage | designed: Admin Panel 70:41386 | P5 | done |
| ADM-027 | Tenant sync | — | — | admin.store.tenant-sync.index | — | — | dropped — PM confirmed (A1: tenants read central live with override → fallback; no copy sync) |
| ADM-030 | Branches list | `admin.shipping.branches.index` | Std; list, delete | admin.branches.index | designed: Admin Panel 71:40880 | P3 | done |
| ADM-030a | ↳ Branch create / edit (stock, zones, default) | `admin.shipping.branches.create` / `.edit` | validation | admin.branches.create, admin.branches.edit | designed: Admin Panel 71:41812 | P3 | done |
| ADM-031 | Fixed shipping costs (per country, price/gram) | `admin.shipping.fixed-costs.index` | Std; list, delete | admin.shipping.fixed-costs | designed: Admin Panel 71:42662 | P4 | done |
| ADM-031a | ↳ Fixed cost create / edit | `admin.shipping.fixed-costs.create` / `.edit` | country unique | admin.shipping.fixed-costs.create, admin.shipping.fixed-costs.edit | designed: Admin Panel 71:48842 | P4 | done |
| ADM-032 | Shipping rules / zones | `admin.shipping.rules.index` | Std; list, delete | admin.shipping.delivery-charges | designed: Admin Panel 71:43578 | P4 | done |
| ADM-032a | ↳ Rule create / edit (country → zones, weight bands) | `admin.shipping.rules.create` / `.edit` | band overlap validation | admin.shipping.delivery-charges.create, admin.shipping.delivery-charges.edit | designed: Admin Panel 71:44486 | P4 | done |
| ADM-033 | Shipping companies list | `admin.shipping.companies.index` | Std; list, toggle active, delete | — (new, A3) | designed: Admin Panel 71:45372 | P4 | done |
| ADM-033a | ↳ Company create / edit (logo, contact, tracking URL pattern) | `admin.shipping.companies.create` / `.edit` | validation | — (new) | designed: Admin Panel 71:46296 | P4 | done |
| ADM-033b | ↳ Tab: Day rules per country (min/max days, default company per country) | tab | CRUD rows | admin.shipping.shipping-days.index | designed: Admin Panel 71:46296 | P4 | done |
| ADM-033c | ↳ Tab: Stop periods (holidays) | tab | CRUD date ranges | admin.shipping.settings (shipping stops) | designed: Admin Panel 71:47208 | P4 | done |
| ADM-033d | ↳ Delivery estimator preview (country + date → range) | panel | live estimate | — (new, A3 estimator) | designed: Admin Panel 71:46296 | P4 | todo |
| ADM-034 | Shipping settings (defaults) | `admin.shipping.settings` | default days, defaults per country | admin.shipping.settings | designed: Admin Panel 71:48071 | P4 | done |
| ADM-035 | Delivery popup days (days 1–10, sum = 100 %) | `admin.shipping.delivery-popup` | per country, sum validation | admin.shipping.delivery-popup.index | designed: Admin Panel 54:19015 · invalid 54:19898 | P4 | done |
| ADM-036 | Shipping fixed-cost resync dev tool | — | — | admin.products-run (DevTools) | — | — | dropped — PM confirmed (dev-only tool; A3: display price computed live = sale + weight × rate; no sync job) |
| ADM-040 | Vendors list | `admin.vendors.index` | Std; filter status/plan, export, delete, impersonate | admin.plans.users | designed: Admin Panel 72:47934 | P2 | done |
| ADM-040a | ↳ Create vendor | `admin.vendors.create` | provision, domain | admin.plans.users.create | designed: Admin Panel 72:48932 | P2 | done |
| ADM-041 | Vendor profile | `admin.vendors.show` | Std; activate, suspend, delete | admin.plans.users.edit | designed: Admin Panel 72:49758 | P2 | done |
| ADM-041a | ↳ Tab: Overview & store info / edit | tab | edit form | AddEditTenant | designed: Admin Panel 72:49758 | P2 | done |
| ADM-041b | ↳ Tab: Plan & subscription (assign plan modal) | tab | manual billing | AssignPackageModal | designed: Admin Panel 72:50656 | P2 | done |
| ADM-041c | ↳ Tab: Domains | tab | list, mark connected | DomainRequestsList | designed: Admin Panel 72:51480 | P2 | done |
| ADM-041d | ↳ Tab: Profit % & affiliate commission | tab | edit | AddEditTenant | designed: Admin Panel 72:51480 | P2 | done |
| ADM-041e | ↳ Tab: Stats & orders | tab | KPIs, orders table | — (new, A4) | designed: Admin Panel 72:51480 | P9 | todo |
| ADM-041f | ↳ Tab: Compliance | tab | verify / needs action | ComplianceDetailPage | designed: Admin Panel 72:51480 | P2 | done |
| ADM-041g | ↳ Tab: Mobile app settings | tab | build config, flavor, store links | TenantMobileAppController | designed: Admin Panel 72:51480 | P11 | todo |
| ADM-041h | ↳ Tab: Limit overrides (AI logo, image search, AI calls) | tab | per-tenant override | admin.settings.ai-logo-limit | designed: Admin Panel 72:51480 | P10 | todo |
| ADM-041i | ↳ Impersonate confirm modal (audited) | modal | reason, opens vendor panel | admin.plans.users.impersonate | designed: Admin Panel 72:52459 | P2 | done |
| ADM-042 | Pending registrations | `admin.vendors.pending` | Std; filter status/plan, delete, resend link | admin.plans.pending-registrations | designed: Admin Panel 73:51922 | P2 | done |
| ADM-043 | Shoppers (read-only, all tenants) | `admin.vendors.shoppers` | Std; filter status, new only, export | admin.customers.index | designed: Admin Panel 73:52854 | P9 | todo |
| ADM-044 | Compliance overview | `admin.vendors.compliance.index` | Std; filter status | admin.compliance.index | designed: Admin Panel 73:53809 | P2 | done |
| ADM-044a | ↳ Compliance detail (verify / needs action + note) | `admin.vendors.compliance.show` | documents preview | admin.compliance.show | designed: Admin Panel 73:54733 | P2 | done |
| ADM-045 | Domains | `admin.vendors.domains.index` | Std | — | designed: Admin Panel 73:55481 | P2 | done |
| ADM-045a | ↳ Tab: Domain requests (connected / pending / removed) | tab | status actions | admin.domains.requests | designed: Admin Panel 73:55481 | P2 | done |
| ADM-045b | ↳ Tab: DNS record templates (CRUD modal) | tab | type, TTL, priority | admin.domains.dns-records | designed: Admin Panel 73:56421 | P2 | done |
| ADM-046 | Plans list | `admin.plans.index` | Std; list, delete | admin.plans.index | designed: Admin Panel 73:57264 | P2 | done |
| ADM-046a | ↳ Plan create / edit (term, price, trial, features, limits, AI flags) | `admin.plans.create` / `.edit` | translations | admin.plans.create, admin.plans.edit | designed: Admin Panel 73:58030 | P2 | done |
| ADM-047 | Subscriptions (registrations & renewals) | `admin.billing.subscriptions.index` | Std; filter status, invoice link, export | — (new, A4) | designed: Admin Panel 73:58869 | P2 | done |
| ADM-050 | Ledger (per tenant balances) | `admin.finance.ledger.index` | Std; filter, export | admin.wallets.index | designed: Admin Panel 74:59026 | P5 | done |
| ADM-050a | ↳ Tenant ledger detail (mark settlement paid modal) | `admin.finance.ledger.show` | amount, method, reference | admin.wallets.show | designed: Admin Panel 74:60018 · 74:68877 | P5 | done |
| ADM-051 | Settlements (vendor → platform) | `admin.finance.settlements.index` | Std; filter gateway/tenant/status | admin.vendor-settlements.index | designed: Admin Panel 74:60809 | P5 | done |
| ADM-052 | Payouts (platform → vendor) | `admin.finance.payouts.index` | Std; list, cancel | admin.finance.payouts.index | designed: Admin Panel 74:61743 | P5 | done |
| ADM-052a | ↳ Create payout for tenant | `admin.finance.payouts.create` | email option | admin.finance.payouts.create | designed: Admin Panel 74:62688 | P5 | done |
| ADM-052b | ↳ Edit payout | `admin.finance.payouts.edit` | status, reference | admin.finance.payouts.edit | designed: Admin Panel 74:68877 | P5 | done |
| ADM-053 | Transactions | `admin.finance.transactions.index` | Std; filter direction/source, detail modal | admin.wallet-transactions.index | designed: Admin Panel 74:63449 · 74:68877 | P5 | done |
| ADM-054 | Invoices | `admin.finance.invoices.index` | Std; filter status | admin.invoices.index | designed: Admin Panel 74:64375 | P2 | done |
| ADM-054a | ↳ Invoice detail (print / PDF) | `admin.finance.invoices.show` | print | admin.invoices.show | designed: Admin Panel 74:65309 | P2 | done |
| ADM-055 | Payment logs | `admin.finance.payment-logs.index` | Std; filter status/gateway | admin.payment-logs.index | designed: Admin Panel 74:66052 | P2 | done |
| ADM-055a | ↳ Payment log detail (refund → affiliate reversal) | `admin.finance.payment-logs.show` | status change | admin.payment-logs.show | designed: Admin Panel 74:66052 | P2 | done |
| ADM-060 | Affiliate accounts | `admin.affiliates.index` | Std; filter type/status | admin.affiliates.index | designed: Admin Panel 74:66931 | P9 | todo |
| ADM-060a | ↳ Edit affiliate modal (commission, status) | modal | activate, suspend | AffiliatesListPage | designed: Admin Panel 74:68877 | P9 | todo |
| ADM-060b | ↳ Pay out modal (amount, reference, attachment) | modal | min/balance validation | AffiliatesListPage | designed: Admin Panel 74:68877 | P9 | todo |
| ADM-060c | ↳ Program settings modal (cookie days, default commission, min payout) | modal | save | AffiliatesListPage | designed: Admin Panel 74:68877 | P9 | todo |
| ADM-061 | Affiliate conversions (reverse modal) | `admin.affiliates.conversions` | Std; filter status, reverse | admin.affiliates.conversions | designed: Admin Panel 74:67899 · 74:68877 | P9 | todo |
| ADM-062 | Affiliate payout requests (details, mark paid, reject) | `admin.affiliates.payouts` | Std | admin.affiliates.payouts | designed: Admin Panel 74:67899 · 74:68877 | P9 | todo |
| ADM-063 | Affiliate promo codes (CRUD modal) | `admin.affiliates.promo-codes` | Std; create, edit, delete | admin.affiliates.coupons | designed: Admin Panel 74:67899 · 74:68877 | P9 | todo |
| ADM-070 | Reports hub | `admin.reports.index` | Std; period/tenant/country filters, CSV/XLSX export on every tab | — (new, A7) | designed: Admin Panel 75:68061 | P9 | todo |
| ADM-070a | ↳ Tab: Sales | `admin.reports.show` | charts + table | — (new) | designed: Admin Panel 75:68061 | P9 | todo |
| ADM-070b | ↳ Tab: Orders report | `admin.reports.show` | monthly series, per tenant | admin.orders.report | designed: Admin Panel 75:69019 | P9 | todo |
| ADM-070c | ↳ Tab: Platform vs vendor profit (finance) | `admin.reports.show` | tabs from old finance reports | admin.finance.reports | designed: Admin Panel 75:69019 | P9 | todo |
| ADM-070d | ↳ Tab: Refunds & returns (return analytics) | `admin.reports.show` | per tenant | admin.orders.returns.analytics | designed: Admin Panel 75:69019 | P9 | todo |
| ADM-070e | ↳ Tab: Shipping (company/zone, delivery performance) | `admin.reports.show` | | — (new) | designed: Admin Panel 75:69019 | P9 | todo |
| ADM-070f | ↳ Tab: Coupons & flash-sale impact | `admin.reports.show` | | — (new) | designed: Admin Panel 75:69019 | P9 | todo |
| ADM-070g | ↳ Tab: Subscriptions / MRR / churn / trials | `admin.reports.show` | | — (new) | designed: Admin Panel 75:69998 | P9 | todo |
| ADM-070h | ↳ Tab: Affiliates (clicks → conversions → payouts) | `admin.reports.show` | | admin.affiliates.reports | designed: Admin Panel 75:69998 | P9 | todo |
| ADM-070i | ↳ Tab: Vendor leaderboard | `admin.reports.show` | | — (new) | designed: Admin Panel 75:69998 | P9 | todo |
| ADM-070j | ↳ Tab: Product performance | `admin.reports.show` | views, add-to-cart, conversion | — (new) | designed: Admin Panel 75:69998 | P9 | todo |
| ADM-070k | ↳ Tab: Customers (new / returning) | `admin.reports.show` | | — (new) | designed: Admin Panel 75:69998 | P9 | todo |
| ADM-070l | ↳ Tab: AI usage (tokens, cost per tenant/feature) | `admin.reports.show` | | — (new, A6c) | designed: Admin Panel 75:69998 | P10 | todo |
| ADM-080 | Blog categories | `admin.content.blog-categories.index` | Std; list, delete | admin.blog.categories | designed: Admin Panel 76:70524 | P6 | done |
| ADM-080a | ↳ Blog category create / edit | `admin.content.blog-categories.create` / `.edit` | translations | admin.blog.categories.create, admin.blog.categories.edit | designed: Admin Panel 76:76659 | P6 | done |
| ADM-081 | Blog posts | `admin.content.blog-posts.index` | Std; list, publish, archive, delete | admin.blog.posts | designed: Admin Panel 76:71416 | P6 | done |
| ADM-081a | ↳ Blog post create / edit (SEO fields, image) | `admin.content.blog-posts.create` / `.edit` | translations | admin.blog.posts.create, admin.blog.posts.edit | designed: Admin Panel 76:72347 | P6 | done |
| ADM-082 | Static pages | `admin.content.pages.index` | Std; activate, archive, delete | admin.pages.index | designed: Admin Panel 76:73162 | P6 | done |
| ADM-082a | ↳ Static page create / edit (target central/storefront/both, compliance flag) | `admin.content.pages.create` / `.edit` | translations | admin.pages.create, admin.pages.edit | designed: Admin Panel 76:74135 | P6 | done |
| ADM-083 | FAQs | `admin.content.faqs.index` | Std; list, delete, sort | admin.faqs.index | designed: Admin Panel 76:74912 | P6 | done |
| ADM-083a | ↳ FAQ create / edit | `admin.content.faqs.create` / `.edit` | translations | admin.faqs.create, admin.faqs.edit | designed: Admin Panel 76:76659 | P6 | done |
| ADM-084 | Newsletter subscribers | `admin.content.newsletter.index` | Std; list, export, delete | admin.newsletter.index | designed: Admin Panel 76:75699 | P6 | done |
| ADM-085 | Default banners (per country) | `admin.storefront.banners.index` | Std; country tabs, sort, delete | admin.settings.default-banners, admin.settings.default-banners.list | designed: Admin Panel 78:76059 | P7 | todo |
| ADM-085a | ↳ Banner create / edit | modal | image, URL, translations | DefaultBannersListPage | designed: Admin Panel 78:82810 | P7 | todo |
| ADM-086 | Themes (13) | `admin.storefront.themes.index` | Std; activate, default, country availability | admin.settings.templates | designed: Admin Panel 78:76835 | P7 | todo |
| ADM-086a | ↳ Theme edit (metadata, preview image, countries) | `admin.storefront.themes.edit` | upload preview | TemplateControlPage | designed: Admin Panel 78:77708 | P7 | todo |
| ADM-087 | Home variants & sections config | `admin.storefront.home-variants.index` | toggle + sort sections, default, preview | admin.settings.home-variants | designed: Admin Panel 78:78508 | P7 | todo |
| ADM-088 | Template parts | `admin.storefront.template-parts.index` | Std; list per theme, delete | admin.settings.template-parts, admin.settings.template-parts.show | designed: Admin Panel 78:79365 | P7 | todo |
| ADM-088a | ↳ Template part create / edit | `admin.storefront.template-parts.create` / `.edit` | | admin.settings.template-parts.create, admin.settings.template-parts.edit | designed: Admin Panel 78:79365 | P7 | todo |
| ADM-089 | Custom theme review queue (Twig) | `admin.storefront.theme-reviews.index` | Std; filter status | admin.settings.blade-themes | designed: Admin Panel 78:80277 | P7 | todo |
| ADM-089a | ↳ Review detail (validation report, versions, preview, approve / reject reason) | `admin.storefront.theme-reviews.show` | approve, reject | BladeThemeQueuePage | designed: Admin Panel 78:81175 | P7 | todo |
| ADM-090 | Return policy (platform default) | `admin.storefront.return-policy.edit` | window, fee, categories, video reasons | admin.settings.return-policy | designed: Admin Panel 78:81991 | P5 | done |
| ADM-091 | Languages (ISO seed) | `admin.localization.languages.index` | Std; activate, free/price, AI price, countries, sort | admin.settings.languages | designed: Admin Panel 79:81928 | P2 | done |
| ADM-091a | ↳ Language create / edit | `admin.localization.languages.create` / `.edit` | flag, direction | admin.settings.languages.create, admin.settings.languages.edit | designed: Admin Panel 79:90884 | P2 | done |
| ADM-091b | ↳ AI catalog translation progress modal (retry) | modal | progress 0–100 %, failed state | LanguagesPage retry | designed: Admin Panel 79:90884 | P10 | todo |
| ADM-092 | Translation files editor | `admin.localization.translations.index` | missing keys, create file, save | admin.settings.translations | designed: Admin Panel 79:82911 | P2 | done |
| ADM-092a | ↳ AI translate keys modal (one / all missing) | modal | progress | — (A6c #4) | designed: Admin Panel 79:90884 | P10 | todo |
| ADM-093 | Translation key locks | `admin.localization.key-locks.index` | lock / unlock | admin.settings.translation-key-access | designed: Admin Panel 79:82911 | P2 | done |
| ADM-094 | Language & AI-translation purchases | `admin.localization.purchases.index` | Std; filter gateway, details | admin.settings.language-purchases | designed: Admin Panel 79:83875 | P2 | done |
| ADM-095 | Currencies | `admin.localization.currencies.index` | Std; default, sync rates button, last sync | admin.settings.currencies | designed: Admin Panel 79:84722 | P2 | done |
| ADM-095a | ↳ Currency create / edit | `admin.localization.currencies.create` / `.edit` | | admin.settings.currencies.create, admin.settings.currencies.edit | designed: Admin Panel 79:90884 | P2 | done |
| ADM-096 | Countries | `admin.localization.countries.index` | Std; toggle active/free, default currency | admin.settings.countries | designed: Admin Panel 79:85682 | P2 | done |
| ADM-100 | Integrations (tabs) | `admin.integrations.index` | Std; secrets masked | — | designed: Admin Panel 51:17971 | P2 | done |
| ADM-100a | ↳ Payment gateways list | `admin.integrations.gateways.index` | toggle, mode test/live | admin.settings.payment-gateways | designed: Admin Panel 51:17971 | P2 | done |
| ADM-100b | ↳ Gateway edit (vendor-payments config + tenant-registration config, fees) | `admin.integrations.gateways.edit` | test connection | admin.settings.payment-gateways.edit | designed: Admin Panel 51:17971 | P2 | done |
| ADM-100c | ↳ Gateway limits tab (notify / block thresholds) | tab | | admin.settings.payment-gateway-limits | designed: Admin Panel 79:86634 | P5 | todo |
| ADM-100d | ↳ Mail tab (send test email) | tab | test result | admin.settings.email-configuration | designed: Admin Panel 53:18783 | P2 | done |
| ADM-100e | ↳ Google login tab | tab | | admin.settings.social-login | designed: Admin Panel 53:18783 | P2 | done |
| ADM-100f | ↳ Apple login tab | tab | | admin.settings.social-login | designed: Admin Panel 53:18783 | P2 | done |
| ADM-100g | ↳ OpenAI tab (models, test connection) | tab | test result, typed errors | admin.settings.ai | designed: Admin Panel 53:18783 | P10 | todo |
| ADM-100h | ↳ Reverb / Pusher tab | tab | | — (new) | designed: Admin Panel 79:87472 | P2 | done |
| ADM-100i | ↳ FCM push tab | tab | | — (new) | designed: Admin Panel 79:87472 | P11 | todo |
| ADM-100j | ↳ Cloudflare / DNS tab | tab | | — (new) | designed: Admin Panel 79:87472 | P2 | done |
| ADM-100k | ↳ Tracking defaults tab | tab | | — (new) | designed: Admin Panel 79:87472 | P8 | todo |
| ADM-101 | General settings (name, logos, favicon, support email, archive days, socials) | `admin.settings.general` | save | admin.settings.general | designed: Admin Panel 79:88365 | P2 | done |
| ADM-102 | Email templates (central) | `admin.settings.email-templates.index` | Std; sync to tenants | admin.settings.email-templates | designed: Admin Panel 79:89188 | P2 | done |
| ADM-102a | ↳ Email template create / edit (per locale, preview) | `admin.settings.email-templates.create` / `.edit` | send test | admin.settings.email-templates.create, admin.settings.email-templates.edit | designed: Admin Panel 79:90124 | P2 | done |
| ADM-110 | Admins | `admin.system.admins.index` | Std; activate, deactivate, delete | admin.admins.index | designed: Admin Panel 80:90095 | P2 | done |
| ADM-110a | ↳ Admin create / edit modal | modal | role, password | AdminsList | designed: Admin Panel 80:98485 | P2 | done |
| ADM-111 | Roles & permissions | `admin.system.roles.index` | Std; delete | admin.admins.roles-permissions | designed: Admin Panel 80:91028 | P2 | done |
| ADM-111a | ↳ Role create / edit (permission matrix) | modal | | RolesPermissionsList | designed: Admin Panel 80:91028 | P2 | done |
| ADM-112 | Audit log | `admin.system.audit.index` | Std; filter actor/tenant/event, export | — (new, decision #20) | designed: Admin Panel 80:91969 | P2 | done |
| ADM-112a | ↳ Audit event detail (diff) | modal | | — (new) | designed: Admin Panel 80:98485 | P2 | done |
| ADM-113 | Maintenance windows | `admin.system.maintenance` | Std; create, end now | admin.settings.maintenance | designed: Admin Panel 80:92875 | P2 | todo |
| ADM-114 | Cache tools | `admin.system.cache` | confirm modal | — | designed: Admin Panel 80:93684 | P2 | todo |
| ADM-114a | ↳ Tab: Main cache | tab | clear | admin.cache.main | designed: Admin Panel 80:93684 | P2 | todo |
| ADM-114b | ↳ Tab: Tenants cache | tab | clear all / one | admin.cache.tenants | designed: Admin Panel 80:93684 | P2 | todo |
| ADM-115 | Developer docs index | `admin.system.docs.index` | | admin.dev-docs.index | designed: Admin Panel 80:94467 | P9 | todo |
| ADM-115a | ↳ Docs topic | `admin.system.docs.show` | | admin.dev-docs.show | designed: Admin Panel 80:94467 | P9 | todo |
| ADM-116 | Support tickets | `admin.support.tickets.index` | Std; filter status/priority | admin.support.index | designed: Admin Panel 80:95200 | P9 | todo |
| ADM-116a | ↳ Ticket detail (live chat, status, priority) | `admin.support.tickets.show` | reply, attach | admin.support.show | designed: Admin Panel 80:96112 | P9 | todo |
| ADM-120 | Error 403 | view `errors.admin.403` | back / login | bootstrap/app.php JSON 403 | designed: Admin Panel 81:97588 | P2 | todo |
| ADM-121 | Error 404 | view `errors.admin.404` | | bootstrap/app.php JSON 404 | designed: Admin Panel 81:98238 | P2 | todo |
| ADM-122 | Error 419 (session expired) | view `errors.admin.419` | reload | bootstrap/app.php 419 | designed: Admin Panel 81:98888 | P2 | todo |
| ADM-123 | Error 500 | view `errors.admin.500` | | — | designed: Admin Panel 81:98906 | P2 | todo |
| ADM-124 | Error 503 (maintenance) | view `errors.admin.503` | | — | designed: Admin Panel 81:98931 | P2 | todo |

## 2. Vendor panel (`{store-domain}/admin`)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| VEN-001 | Login | `vendor.auth.login` | Std; validate, remember, throttle | tenant.login | designed: Vendor Panel (new screens) 82:99816 | P2 | done |
| VEN-002 | Forgot password | `vendor.auth.password.request` | sent state | tenant.password.request | designed: Vendor Panel (new screens) 82:99866 | P2 | done |
| VEN-003 | Reset password | `vendor.auth.password.reset` | invalid token | tenant.password.reset | designed: Vendor Panel (new screens) 82:99866 | P2 | done |
| VEN-004 | Verify email (result) | `vendor.auth.verify` | verified / expired | tenant.verification.verify | designed: Vendor Panel (new screens) 82:99909 | P2 | done |
| VEN-005 | Impersonation accept (+ "impersonating" banner) | `vendor.auth.impersonate` | expired token error, leave | tenant.impersonate.accept | designed: Vendor Panel (new screens) 82:101033 | P2 | done |
| VEN-006 | Panel shell (side menu, top bar, bell, store link, dark/light) | layout `x-vendor::layout` | permission nav, setup badge | TenantNavigation | 12:25829 (side menu), 12:40706 (KPI card), 12:40666, 12:40689, 12:40753 (type scale), 12:18944, 12:18951, 12:25854, 12:25858, 12:40266, 12:40743, 12:40745, 12:40748, 12:40749 | P2 | done |
| VEN-007 | Onboarding (tour / setup) | `vendor.onboarding` | dismiss, complete tour | tenant.onboarding | designed: Vendor Panel (new screens) 82:100227 · wizard 82:99932 | P2 | done — tour, checklist and the 4-step wizard (countries → languages → theme → launch) work in the browser; the Theme step and checklist link to `vendor.store.themes.index` (VEN-030, P8) |
| VEN-007a | ↳ Tab: Tour | tab | complete | tenant.onboarding.tour.complete | designed: Vendor Panel (new screens) 82:100227 | P2 | done |
| VEN-007b | ↳ Tab: Setup checklist (logo upload, payment readiness skip) | tab | 8 steps, progress | tenant.onboarding.logo, .payment-readiness.skip | designed: Vendor Panel (new screens) 82:100227 | P2 | done |
| VEN-008 | Setup progress widget | `vendor.widgets.setup-progress` | % done, links | tenant.widgets.setup-progress | designed: Vendor Panel (new screens) 82:100227 | P2 | done |
| VEN-009 | Dashboard | `vendor.dashboard` | Std; period selector, KPIs, latest orders, top customers/products | tenant.dashboard | 12:11267, m 12:18171 | P8 | partial — P2 foundation at the panel home: welcome header (View store), store hero with "Store power" = setup progress and readiness chips, setup widget (VEN-008), store status card. KPI cards, period selector, latest orders / top customers wait for their P5 / P8 data (no sample numbers) |
| VEN-009a | ↳ Owed-to-platform alert → settle | widget | pay link | dashboard `_hero`, `_pay-alert` | 12:11267 | P8 | todo |
| VEN-009b | ↳ Partner program banner | widget | copy link | dashboard `_partner` | 12:11267 | P9 | todo |
| VEN-009c | ↳ Opportunities + flash-sale suggestion modal | widget + modal | create flash sale | dashboard `_opportunities`, `_opp-flash-sale-modal` | 12:11267 | P8 | todo |
| VEN-009d | ↳ New-in central products widget | widget | add to store | dashboard `_new-in` | 12:11267 | P8 | todo |
| VEN-009e | ↳ AI ads widget → AI Advertising Studio | widget | opens VEN-010c | dashboard `_ads` | 12:11267 | P10 | todo |
| VEN-009f | ↳ Live store preview by country | widget | country switch | dashboard `_storefront` | 12:11267 | P8 | todo |
| VEN-010 | Products (central, with markup) | `vendor.catalog.products.index` | Std; toggle active/featured, delete, export | tenant.products.index | 12:12968, m 12:21178 | P3 | done |
| VEN-010a | ↳ Price list modal (markup per country / variant, preview) | modal | apply job progress | tenant.products.price-list | designed: Vendor Panel (new screens) 83:104181 | P3 | done |
| VEN-010b | ↳ AI price finder modal | modal | avg/median/min/max, hits, quota error | tenant.products.ai-price | designed: Vendor Panel (new screens) 83:104181 | P10 | todo |
| VEN-010c | ↳ AI Advertising Studio / social posts modal (4 steps) | modal | platform, language, image, copy/download | tenant.products.social | 12:15119 | P10 | todo |
| VEN-010d | ↳ Share modal | modal | copy, share links | tenant.products.share | designed: Vendor Panel (new screens) 83:104181 | P8 | todo |
| VEN-010e | ↳ Shipping details modal (per destination fee) | modal | country select | — (Figma) | 12:16200 | P4 | done |
| VEN-011 | New central products | `vendor.catalog.products.new` | Std; import selected / all | tenant.products.new | m 12:21874 | P3 | done |
| VEN-011a | ↳ New product preview | `vendor.catalog.products.new.show` | add | tenant.products.new.show | designed: Vendor Panel (new screens) 83:104181 | P3 | todo |
| VEN-012 | Product edit (overrides) | `vendor.catalog.products.edit` | Std; save, protected central fields (422) | tenant.products.edit, tenant.products.create | designed: Vendor Panel (new screens) 83:100772 | P3 | done |
| VEN-012a | ↳ Tab: Pricing per variant / country | tab | real/sell/margin | SaveProductRequest | designed: Vendor Panel (new screens) 83:100772 | P3 | done |
| VEN-012b | ↳ Tab: Content & translations (+ "Translate with AI") | tab | preview, regenerate | ProductAiTranslationController | designed: Vendor Panel (new screens) 83:101628 | P10 | todo |
| VEN-012c | ↳ Tab: Stock & weight overrides | tab | | SaveProductRequest | designed: Vendor Panel (new screens) 83:101628 | P3 | done |
| VEN-012d | ↳ Tab: Categories & badges | tab | | SaveProductRequest | designed: Vendor Panel (new screens) 83:101628 | P3 | done |
| VEN-012e | ↳ Central snapshot (read-only) | panel | | tenant.products.central-snapshot, tenant.products.central-search | designed: Vendor Panel (new screens) 83:101628 | P3 | done |
| VEN-013 | Edit requests history | `vendor.catalog.edit-requests.index` | Std; status | tenant.products.edit-requests | designed: Vendor Panel (new screens) 83:102290 | P3 | done |
| VEN-013a | ↳ Submit edit request modal | modal | fields diff, files | ProductController | designed: Vendor Panel (new screens) 83:104181 | P3 | done |
| VEN-014 | Product sort | `vendor.catalog.products.sort` | drag & drop | tenant.products.sort | designed: Vendor Panel (new screens) 83:102998 | P3 | done |
| VEN-015 | Today's chances (opportunities) | `vendor.catalog.opportunities` | Std; add to store, filter | tenant.todays-chances | 12:12050, m 12:18958 | P3 | done |
| VEN-015a | ↳ Select markets modal | modal | countries, search | — (Figma) | 12:14756 | P3 | todo |
| VEN-015b | ↳ Select markets: already added state | modal state | | — (Figma) | 12:14923 | P3 | todo |
| VEN-016 | Own products list | `vendor.catalog.own-products.index` | Std; delete, toggle | tenant.own-products.index | 12:13462 | P3 | done |
| VEN-016a | ↳ Own product inner page | `vendor.catalog.own-products.show` | | — (Figma) | 12:13819 | P3 | todo |
| VEN-016b | ↳ Inventory (own products) | `vendor.catalog.own-products.inventory` | stock edit | — (Figma) | 12:17880, m 12:23071 | P3 | done |
| VEN-016c | ↳ Own product create / edit (countries, variants, AI translate, AI price) | `vendor.catalog.own-products.create` / `.edit` | validation | tenant.own-products.create, tenant.own-products.edit | 12:17411, m 12:22819 | P3 | done |
| VEN-017 | Categories | `vendor.catalog.categories.index` | Std; toggle, delete | tenant.categories.index | 12:14144 | P3 | done |
| VEN-017a | ↳ Category inner page (products) | `vendor.catalog.categories.products` | order products | tenant.categories.products | 12:14465, m 12:22248 | P3 | done |
| VEN-017b | ↳ Category create / edit | `vendor.catalog.categories.create` / `.edit` | thumbnail | tenant.categories.create, tenant.categories.edit | 12:17672, m 12:22624 | P3 | done |
| VEN-017c | ↳ Category sort | `vendor.catalog.categories.sort` | drag & drop | tenant.categories.sort | designed: Vendor Panel (new screens) 83:102998 | P3 | done |
| VEN-018 | Badges | `vendor.catalog.badges.index` | Std | tenant.badges.index | designed: Vendor Panel (new screens) 83:103600 | P3 | done |
| VEN-018a | ↳ Badge products (search, assign category) | `vendor.catalog.badges.show` | assign, remove | tenant.badges.show, tenant.badges.search | designed: Vendor Panel (new screens) 83:103600 | P3 | done |
| VEN-018b | ↳ Badge sort | `vendor.catalog.badges.sort` | drag & drop | tenant.badges.sort | designed: Vendor Panel (new screens) 83:103600 | P3 | done |
| VEN-020 | Orders | `vendor.sales.orders.index` | Std; filter, export | tenant.orders.index, tenant.orders.export | 12:15191, m 12:23476 | P5 | done |
| VEN-021 | Order detail (totals, settlement figures, print) | `vendor.sales.orders.show` | Std | tenant.orders.show | designed: Vendor Panel (new screens) 84:102416 | P5 | done |
| VEN-021a | ↳ Cancel modal (reason codes) | modal | | OrdersController@cancel | designed: Vendor Panel (new screens) 84:107236 | P5 | done |
| VEN-021b | ↳ Manual refund modal | modal | | OrdersController@storeRefund | designed: Vendor Panel (new screens) 84:107236 | P5 | done |
| VEN-021c | ↳ Update shipping status (own-product orders only) | modal | 403 otherwise | OrdersController@updateShippingStatus | designed: Vendor Panel (new screens) 84:107236 | P5 | done |
| VEN-021d | ↳ Pay platform: settlement breakdown → gateway → result | `vendor.finance.settle.*` | success / cancel states | tenant.finance.vendor-purchase-settle, tenant.finance.vendor-purchase-settle.breakdown, tenant.vendor-settlement.charge, tenant.vendor-settlement.cancel, tenant.vendor-settlement.success | designed: Vendor Panel (new screens) 84:103244 | P5 | done |
| VEN-022 | Returns | `vendor.sales.returns.index` | Std; filter | tenant.returns.index | designed: Vendor Panel (new screens) 84:103852 | P5 | done |
| VEN-022a | ↳ Return detail (all transitions: approve, reject, info, received, inspect, refund, exchange, close, note) | `vendor.sales.returns.show` | one modal per action | tenant.returns.show | designed: Vendor Panel (new screens) 84:104582 | P5 | done |
| VEN-023 | Refunds (retry, complete, reject) | `vendor.sales.refunds.index` | Std | tenant.refunds.index | designed: Vendor Panel (new screens) 84:105181 | P5 | done |
| VEN-024 | Return analytics | `vendor.sales.returns.analytics` | charts, export | tenant.returns.analytics | designed: Vendor Panel (new screens) 84:105181 | P8 | todo |
| VEN-025 | Customers | `vendor.sales.customers.index` | Std; export, delete | tenant.customers.index, tenant.customers.export | 12:15544, m 12:23838 | P8 | done |
| VEN-025a | ↳ Customer create | `vendor.sales.customers.create` | intl-tel-input | tenant.customers.create | designed: Vendor Panel (new screens) 84:107236 | P8 | done |
| VEN-025b | ↳ Customer detail (profile, toggle active, payments) | `vendor.sales.customers.show` | | tenant.customers.show | designed: Vendor Panel (new screens) 84:105854 | P8 | done |
| VEN-025c | ↳ Address create / edit modal (cities by country) | modal | | tenant.customers.addresses.show, tenant.cities.by-country | designed: Vendor Panel (new screens) 84:107236 | P8 | done |
| VEN-026 | Newsletter subscribers | `vendor.sales.subscribers.index` | Std; export, delete | tenant.settings.subscribers, tenant.settings.subscribers.export | designed: Vendor Panel (new screens) 84:106555 | P8 | done |
| VEN-030 | Themes (per country theme + home variant) | `vendor.store.themes.index` | activate, per-country | tenant.store.themes | designed: Vendor Panel (new screens) 85:104756 | P7 | done — `ThemeController@index`: store default + selling-country tabs, family cards (Activate / Use in countries / Preview), home-variant section, reset to default; signed storefront preview (decision #85) |
| VEN-030a | ↳ Theme countries modal | modal | | tenant.store.themes.countries | designed: Vendor Panel (new screens) 85:110108 | P7 | done — remote modal `store.themes.countries` (selling countries + make default) |
| VEN-030b | ↳ Country home-variant modal | modal | | tenant.store.themes.country-variants | designed: Vendor Panel (new screens) 85:110108 | P7 | done — remote modal `store.themes.variant` (radio list of the family variants) |
| VEN-031 | Home variants | `vendor.store.home-variants` | select | tenant.store.home-variants | designed: Vendor Panel (new screens) 85:104756 | P7 | done — `vendor.store.home-variants`: per-country table, change variant (030b), use store default, preview |
| VEN-032 | Page builder (sections order / visibility) | `vendor.store.page-builder` | drag, toggle, preview | tenant.store.page-builder | designed: Vendor Panel (new screens) 85:105527 | P7 | todo |
| VEN-033 | Pages | `vendor.store.pages.index` | Std; delete | tenant.store.pages | designed: Vendor Panel (new screens) 85:106165 | P8 | todo |
| VEN-033a | ↳ Page create / edit | `vendor.store.pages.create` / `.edit` | translations | tenant.store.pages.create, tenant.store.pages.edit | designed: Vendor Panel (new screens) 85:106165 | P8 | todo |
| VEN-034 | Appearance | `vendor.store.appearance` | save per tab | tenant.store.appearance | designed: Vendor Panel (new screens) 85:106903 | P8 | todo |
| VEN-034a | ↳ Tab: Logo (builder + AI logo generator) | tab | generate, limit reached | AppearanceController, LogoGeneratorService | designed: Vendor Panel (new screens) 85:106903 | P10 | todo |
| VEN-034b | ↳ Tab: Colors & fonts (LTR/RTL, reset) | tab | | AppearanceController | designed: Vendor Panel (new screens) 85:107559 | P8 | todo |
| VEN-034c | ↳ Tab: Promo banner | tab | | AppearanceController | designed: Vendor Panel (new screens) 85:107559 | P8 | todo |
| VEN-034d | ↳ Tab: Top header links | tab | | AppearanceController | designed: Vendor Panel (new screens) 85:107559 | P8 | todo |
| VEN-034e | ↳ Tab: Footer | tab | | AppearanceController | designed: Vendor Panel (new screens) 85:107559 | P8 | todo |
| VEN-034f | ↳ Tab: Social links (CRUD) | tab | | tenant.store.appearance.social.index, tenant.store.appearance.social.show | designed: Vendor Panel (new screens) 85:107559 | P8 | todo |
| VEN-035 | Banners (country tabs, show/hide admin banners, sort) | `vendor.store.banners.index` | Std; plan limit | tenant.store.banners.index, tenant.store.banners | designed: Vendor Panel (new screens) 85:108271 | P8 | done |
| VEN-035a | ↳ Banner create / edit | modal | | tenant.store.banners.show | designed: Vendor Panel (new screens) 85:110108 | P8 | done |
| VEN-036 | Custom themes (Twig): upload ZIP, validation report, versions, submit, activate, delete | `vendor.store.custom-themes.index` | Std; rejected reason | tenant.store.blade-theme | designed: Vendor Panel (new screens) 85:108862 | P7 | todo |
| VEN-036a | ↳ Starter kit download | `vendor.store.custom-themes.starter-kit` | download | tenant.store.blade-theme.starter-kit | designed: Vendor Panel (new screens) 85:108862 | P7 | todo |
| VEN-036b | ↳ Theme settings (settings-schema form) | `vendor.store.custom-themes.settings` | | — (new, §D) | designed: Vendor Panel (new screens) 85:108862 | P7 | todo |
| VEN-037 | Mobile app settings | `vendor.store.mobile-app` | Std; build status | tenant.store.mobile-app | designed: Vendor Panel (new screens) 85:109494 | P11 | todo |
| VEN-037a | ↳ App theme & colors | `vendor.store.mobile-app.themes` | | tenant.store.mobile-themes | designed: Vendor Panel (new screens) 85:109494 | P11 | todo |
| VEN-040 | Coupons (own + read-only central tab) | `vendor.marketing.coupons.index` | Std; per country | tenant.store.coupons.index, tenant.store.coupons.list | designed: Vendor Panel (new screens) 87:107268 | P5 | done |
| VEN-040a | ↳ Coupon create / edit | modal | | tenant.store.coupons.show | designed: Vendor Panel (new screens) 87:111915 | P5 | done |
| VEN-041 | Flash sales (own + read-only central tab) | `vendor.marketing.flash-sales.index` | Std; per country | tenant.store.flash-sales.index, tenant.store.flash-sales | designed: Vendor Panel (new screens) 87:108154 | P5 | done |
| VEN-041a | ↳ Flash sale create / edit (product search, banner) | modal | | tenant.store.flash-sales.show, tenant.store.flash-sales.products.search | designed: Vendor Panel (new screens) 87:111915 | P5 | done |
| VEN-041b | ↳ Attach product to flash sale (from product) | modal | | tenant.store.flash-sales.available-for-product | designed: Vendor Panel (new screens) 87:111915 | P5 | done |
| VEN-045 | Analytics (tabs) | `vendor.analytics` | Std; period filter | tenant.analytics | — | P8 | todo |
| VEN-045a | ↳ Tab: Order analytics | `vendor.analytics?tab=orders` | charts | tenant.analytics.orders.data.monthly, tenant.analytics.orders.data.status | 12:16223, m 12:24731 | P8 | todo |
| VEN-045b | ↳ Tab: Shipping analytics | `vendor.analytics?tab=shipping` | | tenant.analytics.shipping.data.monthly | designed: Vendor Panel (new screens) 87:108835 | P8 | todo |
| VEN-045c | ↳ Tab: Customer lifetime value | `vendor.analytics?tab=clv` | | tenant.analytics.clv.data | 12:16815, m 12:25310 | P8 | todo |
| VEN-045d | ↳ Tab: Product profitability | `vendor.analytics?tab=profitability` | | tenant.analytics.profitability.data | designed: Vendor Panel (new screens) 87:108835 | P8 | todo |
| VEN-050 | Requests hub (product / manufacturing / brand tabs, unread badges) | `vendor.requests.index` | Std | — (Figma) | 12:15910, m 12:24211 | P3 | done |
| VEN-050a | ↳ Product requests list | `vendor.requests.product.index` | | tenant.product-requests.index | 12:15910 | P3 | done |
| VEN-050b | ↳ New product request ("Order new product" modal) | `vendor.requests.product.create` | files, target markets | tenant.product-requests.create | 12:14977 | P3 | done |
| VEN-050c | ↳ Product request detail (live chat, files) | `vendor.requests.product.show` | | tenant.product-requests.show | designed: Vendor Panel (new screens) 87:109541 | P3 | done |
| VEN-050d | ↳ Manufacturing list (export) | `vendor.requests.manufacturing.index` | | tenant.manufacturing.index, tenant.manufacturing.export | 12:15910 | P3 | done |
| VEN-050e | ↳ New manufacturing request | `vendor.requests.manufacturing.create` | product search | tenant.manufacturing.create, tenant.manufacturing.products.search | 12:16096, m 12:24527 | P3 | done |
| VEN-050f | ↳ Manufacturing detail (chat, cancel, pay request) | `vendor.requests.manufacturing.show` | | tenant.manufacturing.show | designed: Vendor Panel (new screens) 87:110114 | P3 | todo |
| VEN-050g | ↳ Brand requests list | `vendor.requests.brand.index` | delete | tenant.brand-requests.index | 12:15910 | P3 | done |
| VEN-050h | ↳ New brand request | `vendor.requests.brand.create` | | tenant.brand-requests.create | designed: Vendor Panel (new screens) 87:111915 | P3 | done |
| VEN-050i | ↳ Brand request detail (chat, pay request) | `vendor.requests.brand.show` | | tenant.brand-requests.show | designed: Vendor Panel (new screens) 87:110114 | P3 | todo |
| VEN-050j | ↳ Request payment result (success / cancel) | `vendor.requests.payment.result` | | tenant.manufacturing-payment.charge, tenant.manufacturing-payment.cancel, tenant.manufacturing-payment.success, tenant.brand-request-payment.charge, tenant.brand-request-payment.cancel, tenant.brand-request-payment.success | designed: Vendor Panel (new screens) 87:110730 | P5 | todo |
| VEN-055 | Partner program (link, stats, conversions, payouts, SSO) | `vendor.partner.index` | Std; copy, share | tenant.partner-program, tenant.finance.wallet.partner.conversions.data, tenant.finance.wallet.partner.payouts.data | designed: Vendor Panel (new screens) 87:111264 | P9 | todo |
| VEN-055a | ↳ Withdraw request modal | modal | min / requestable | PartnerWithdrawRequest | designed: Vendor Panel (new screens) 87:111915 | P9 | todo |
| VEN-060 | Subscription & balance | `vendor.finance.subscription` | Std; plan, usage vs limits | tenant.finance.wallet | designed: Vendor Panel (new screens) 88:109601 | P2 | done |
| VEN-060a | ↳ Subscribe / renew / upgrade modal → gateway | modal | | SubscriptionPaymentController | designed: Vendor Panel (new screens) 88:113398 | P2 | done |
| VEN-060b | ↳ Subscription payment result (success / cancel) | `vendor.finance.subscription.result` | | tenant.subscription-payment.charge, tenant.subscription-payment.cancel, tenant.subscription-payment.success | designed: Vendor Panel (new screens) 88:113398 | P2 | partial (QA-P5 re-check: gateway-error state verified in browser; paid/cancel result pages only covered by Pest, QA has no working subscription gateway) |
| VEN-060c | ↳ Tab: Subscriptions history | tab | | tenant.finance.wallet.subscriptions.data | designed: Vendor Panel (new screens) 88:109601 | P2 | done |
| VEN-060d | ↳ Tab: Transactions | tab | | tenant.finance.wallet.transactions.data | designed: Vendor Panel (new screens) 88:109601 | P5 | done |
| VEN-061 | Billing (invoices) | `vendor.finance.invoices.index` | Std | tenant.finance.billing, tenant.finance.billing.data | designed: Vendor Panel (new screens) 88:110393 | P2 | done |
| VEN-061a | ↳ Invoice detail (print / PDF) | `vendor.finance.invoices.show` | | tenant.finance.billing.detail | designed: Vendor Panel (new screens) 88:110393 | P2 | done |
| VEN-062 | Vendor purchases (owed to platform) | `vendor.finance.purchases.index` | Std; export, settle | tenant.finance.vendor-purchases, tenant.finance.vendor-purchases.export | designed: Vendor Panel (new screens) 88:111108 | P5 | done |
| VEN-063 | Settlement payments | `vendor.finance.settlements.index` | Std | tenant.finance.settlement-payments | designed: Vendor Panel (new screens) 88:111108 | P5 | done |
| VEN-064 | Payouts received | `vendor.finance.payouts.index` | Std | tenant.finance.payouts | designed: Vendor Panel (new screens) 88:111108 | P5 | done |
| VEN-070 | Settings: Account (profile, password) | `vendor.settings.account` | save | tenant.settings.account | designed: Vendor Panel (new screens) 90:111315 | P8 | todo |
| VEN-071 | Settings: General (default profit %) | `vendor.settings.general` | save | tenant.settings.general | designed: Vendor Panel (new screens) 90:112042 | P8 | todo |
| VEN-071a | ↳ Country change request modal | modal | pending state | GeneralSettingsController | designed: Vendor Panel (new screens) 91:119876 | P8 | todo |
| VEN-071b | ↳ Category change request modal | modal | pending state | GeneralSettingsController@submitCategoryRequest | designed: Vendor Panel (new screens) 91:119876 | P8 | todo |
| VEN-072 | Settings: Currencies | `vendor.settings.currencies` | toggle, default | tenant.settings.currencies | designed: Vendor Panel (new screens) 90:112623 | P8 | todo |
| VEN-073 | Settings: Languages (enable free, buy paid, default, sort) | `vendor.settings.languages` | Std | tenant.settings.languages-manage, tenant.settings.languages | designed: Vendor Panel (new screens) 90:113194 | P2 | todo |
| VEN-073a | ↳ Buy language checkout → result | `vendor.settings.languages.purchase` | success / cancel | tenant.language-purchase.charge, tenant.language-purchase.cancel, tenant.language-purchase.success | designed: Vendor Panel (new screens) 90:113194 | P2 | todo |
| VEN-073b | ↳ Purchases history tab | tab | | tenant.finance.buy-languages | designed: Vendor Panel (new screens) 90:113194 | P2 | todo |
| VEN-074 | Settings: AI translation (full store, progress, history, tokens, cost) | `vendor.settings.ai-translation` | run, poll status, fail state | tenant.settings.ai-translation, tenant.settings.ai-translation.status | designed: Vendor Panel (new screens) 90:113836 | P10 | todo |
| VEN-074a | ↳ Buy AI-translation add-on → result | `vendor.settings.ai-translation.purchase` | | tenant.ai-translation-purchase.charge, tenant.ai-translation-purchase.cancel, tenant.ai-translation-purchase.success | designed: Vendor Panel (new screens) 90:113836 | P10 | todo |
| VEN-075 | Settings: Store translations (keys, locked keys, AI translate) | `vendor.settings.translations` | save key, AI one/all | tenant.settings.translations | designed: Vendor Panel (new screens) 90:114518 | P8 | todo |
| VEN-076 | Settings: Staff | `vendor.settings.staff.index` | Std; activate, delete | tenant.settings.admins | designed: Vendor Panel (new screens) 90:115176 | P8 | done |
| VEN-076a | ↳ Staff create / edit | modal | | tenant.settings.admins.show | designed: Vendor Panel (new screens) 90:115176 | P8 | done |
| VEN-077 | Settings: Roles & permissions | `vendor.settings.roles.index` | Std | tenant.settings.roles-permissions | designed: Vendor Panel (new screens) 90:115822 | P8 | done |
| VEN-077a | ↳ Role create / edit | modal | | tenant.settings.roles-permissions.show | designed: Vendor Panel (new screens) 90:115822 | P8 | done |
| VEN-078 | Settings: Payment gateways | `vendor.settings.payment-gateways.index` | Std; set primary | tenant.settings.payment-gateways | designed: Vendor Panel (new screens) 90:116465 | P5 | done |
| VEN-078a | ↳ Gateway credentials (own creds, sandbox, check connection) | `vendor.settings.payment-gateways.show` | test result | tenant.settings.payment-gateways.show | designed: Vendor Panel (new screens) 90:116465 | P5 | done |
| VEN-078b | ↳ Payment readiness checklist | `vendor.settings.payment-readiness` | | tenant.settings.payment-readiness | designed: Vendor Panel (new screens) 90:116465 | P5 | todo |
| VEN-079 | Settings: Return policy | `vendor.settings.return-policy` | override platform default | tenant.settings.return-policy | designed: Vendor Panel (new screens) 90:117144 | P5 | todo |
| VEN-080 | Settings: Email templates | `vendor.settings.email-templates.index` | resync from central | tenant.settings.email-templates | designed: Vendor Panel (new screens) 91:114835 | P8 | todo |
| VEN-080a | ↳ Email template edit (per locale) | `vendor.settings.email-templates.edit` | preview | tenant.settings.email-templates.edit | designed: Vendor Panel (new screens) 91:114835 | P8 | todo |
| VEN-081 | Settings: Mail (SMTP, send test) | `vendor.settings.mail` | test result | tenant.settings.mail | designed: Vendor Panel (new screens) 91:115511 | P8 | todo |
| VEN-082 | Settings: Domains (add, DNS check) | `vendor.settings.domains.index` | Std | tenant.settings.domains | designed: Vendor Panel (new screens) 91:116087 | P2 | todo |
| VEN-082a | ↳ Domain detail (DNS records, status) | `vendor.settings.domains.show` | re-check | tenant.settings.domains.show | designed: Vendor Panel (new screens) 91:116087 | P2 | todo |
| VEN-083 | Settings: Tracking pixels (Meta, TikTok, Snap, GA4) | `vendor.settings.tracking` | | tenant.settings.tracking | designed: Vendor Panel (new screens) 91:116736 | P8 | todo |
| VEN-084 | Settings: Compliance center (identity, company, bank, documents, accept terms) | `vendor.settings.compliance.edit` (+ `.update`, `.submit`, `.cities`, `.documents.show`, `.documents.destroy`) | review status | tenant.settings.compliance, tenant.settings.compliance.cities-by-country, tenant.compliance.accept | designed: Vendor Panel (new screens) 91:117308 | P8 | done |
| VEN-085 | Settings: API keys (public API v1) | `vendor.settings.api-keys` | create, revoke, copy once | — (new, §D) | designed: Vendor Panel (new screens) 91:117954 | P8 | todo |
| VEN-086 | Settings: Audit log | `vendor.settings.audit` | Std; filter | — (new, §B) | designed: Vendor Panel (new screens) 91:118575 | P8 | todo |
| VEN-087 | Settings: Notification preferences | `vendor.settings.notifications` | toggles | — (new) | designed: Vendor Panel (new screens) 91:119215 | P9 | todo |
| VEN-090 | Notifications | `vendor.notifications.index` | Std; feed, open, mark read / all | tenant.notifications.index, tenant.notifications.feed, tenant.notifications.latest, tenant.notifications.open | 12:17282, m 12:24611 | P9 | todo |
| VEN-091 | Support tickets | `vendor.support.index` | Std | tenant.support.index | designed: Vendor Panel (new screens) 88:111731 | P9 | todo |
| VEN-091a | ↳ New ticket | `vendor.support.create` | attach | tenant.support.create | designed: Vendor Panel (new screens) 88:113398 | P9 | todo |
| VEN-091b | ↳ Ticket thread (live) | `vendor.support.show` | reply | tenant.support.show, tenant.support.thread | designed: Vendor Panel (new screens) 88:111731 | P9 | todo |
| VEN-092 | Help center | `vendor.help.index` | search | tenant.help.index | designed: Vendor Panel (new screens) 88:112298 | P9 | todo |
| VEN-092a | ↳ Help article | `vendor.help.article` | | tenant.help.article | designed: Vendor Panel (new screens) 88:112298 | P9 | todo |
| VEN-093 | UI kit / component gallery (internal) | `vendor.ui-kit` | | tenant.ui-kit, tenant.ui-kit.data | designed: Vendor Panel (new screens) 92:130313 | P2 | todo |
| VEN-094 | Plan-limit reached / upgrade modal | modal | upgrade CTA | PlanLimitService | designed: Vendor Panel (new screens) 82:102216 | P2 | todo |
| VEN-095 | Subscription expired / store suspended screen | view | renew CTA | — (new) | designed: Vendor Panel (new screens) 82:101634 | P2 | todo |
| VEN-096 | Error 403 | view | | AJAX contract 403 | designed: Vendor Panel (new screens) 82:102128 | P2 | todo |
| VEN-097 | Error 404 | view | | — | designed: Vendor Panel (new screens) 82:102150 | P2 | todo |
| VEN-098 | Error 419 | view | reload | AJAX contract 419 | designed: Vendor Panel (new screens) 82:102172 | P2 | todo |
| VEN-099 | Error 500 | view | | — | designed: Vendor Panel (new screens) 82:102194 | P2 | todo |

## 3. Affiliate panel (`{central}/affiliate`)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| AFF-001 | Login | `affiliate.auth.login` | Std; throttle, inactive/pending error | affiliate.login | designed: Affiliate & Owner 119:100054 · error 119:100128 · m 119:101143 | P9 | todo |
| AFF-002 | Register | `affiliate.auth.register` | validation | affiliate.register | designed: Affiliate & Owner 119:100207 · validation 119:100321 · m 119:101191 | P9 | todo |
| AFF-003 | Pending approval notice | `affiliate.auth.pending` | | RegisterPage (pending) | designed: Affiliate & Owner 119:100433 | P9 | todo |
| AFF-004 | Forgot password | `affiliate.auth.password.request` | sent state | — (new, §C) | designed: Affiliate & Owner 119:100492 · sent 119:100552 | P9 | todo |
| AFF-005 | Reset password | `affiliate.auth.password.reset` | invalid token | — (new, §C) | designed: Affiliate & Owner 119:100603 · invalid token 119:100669 | P9 | todo |
| AFF-006 | Verify email | `affiliate.auth.verify` | resend, expired | — (new) | designed: Affiliate & Owner 119:100720 · expired 119:100776 | P9 | todo |
| AFF-007 | SSO landing (from vendor partner program) | `affiliate.sso` | invalid / used / expired token | affiliate.sso | designed: Affiliate & Owner 119:100823 · invalid/used/expired 119:100873 | P9 | todo |
| AFF-008 | Panel shell | layout `x-affiliate::layout` | bell, locale | layouts.affiliate | designed: Affiliate & Owner shell 121:100334 · sidebar comp 118:99699 · m 121:102977 | P9 | todo |
| AFF-009 | Dashboard | `affiliate.dashboard` | Std; stat cards, recent conversions | affiliate.dashboard | designed: Affiliate & Owner 121:100334 · m 121:102977 | P9 | todo |
| AFF-010 | Links & promo codes | `affiliate.links` | copy link, clicks | affiliate.links | designed: Affiliate & Owner 121:100742 | P9 | todo |
| AFF-011 | Conversions | `affiliate.conversions` | Std; filter status | affiliate.conversions | designed: Affiliate & Owner 121:101191 · m 121:103181 | P9 | todo |
| AFF-012 | Payouts (history + requests) | `affiliate.payouts` | Std | affiliate.payouts | designed: Affiliate & Owner 121:101567 | P9 | todo |
| AFF-012a | ↳ Request payout modal | modal | min / max requestable | PayoutsPage | designed: Affiliate & Owner 121:101861 · validation 121:103254 · sheet m 121:103107 | P9 | todo |
| AFF-013 | Profile | `affiliate.profile` | save | affiliate.profile | designed: Affiliate & Owner 121:102088 | P9 | todo |
| AFF-013a | ↳ Tab: Payout details (PayPal, bank, IBAN) | tab | | ProfilePage | designed: Affiliate & Owner 121:102328 | P9 | todo |
| AFF-013b | ↳ Tab: Change password | tab | | ProfilePage | designed: Affiliate & Owner 121:102571 | P9 | todo |
| AFF-014 | Notifications | `affiliate.notifications.index` | Std; mark read | — (new, §C) | designed: Affiliate & Owner 121:102771 | P9 | todo |
| AFF-015 | Error 403 / 404 / 419 / 500 | views | | — | designed: Affiliate & Owner 403 119:100939 · 404 119:100989 · 419 119:101040 · 500 119:101091 | P9 | todo |

## 4. Owner portal (`{central}/my-account`)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| OWN-001 | My stores (dashboard) | `owner.dashboard` | Std; open store panel (SSO), store status | owner.dashboard (redirect) | designed: Affiliate & Owner 122:102288 · m 122:105386 · sidebar comp 118:99795 | P2 | done |
| OWN-002 | Select store (after login, many stores) | `owner.select-store` | select | owner.select-tenant | designed: Affiliate & Owner 122:105321 | P2 | done |
| OWN-003 | Subscriptions (all stores) | `owner.subscriptions` | Std; renew link | owner.subscriptions | designed: Affiliate & Owner 122:102615 | P2 | done |
| OWN-004 | Domains | `owner.domains` | Std; remove | owner.domains | designed: Affiliate & Owner 122:103025 | P2 | done |
| OWN-004a | ↳ Add domain modal | modal | regex validation | DomainsList addDomain | designed: Affiliate & Owner 122:103429 · validation 122:103792 · sheet m 122:105483 | P2 | done |
| OWN-004b | ↳ Edit domain modal | modal | | DomainsList updateDomain | designed: Affiliate & Owner 122:103838 | P2 | done |
| OWN-004c | ↳ DNS check result | panel | pass / fail per record | DomainsList checkDns | designed: Affiliate & Owner 122:103882 | P2 | done |
| OWN-005 | Invoices (all stores) | `owner.invoices` | Std; download | — (new) | designed: Affiliate & Owner 122:104137 | P2 | done |
| OWN-006 | Profile & password / linked Google-Apple | `owner.profile` | save | — (new) | designed: Affiliate & Owner 122:104562 | P2 | done |
| OWN-007 | Add another store | `owner.stores.create` | → register flow WEB-016 | — (new, §D one owner many stores) | designed: Affiliate & Owner 122:104860 | P2 | done |
| OWN-008 | Notifications | `owner.notifications` | Std | — (new) | designed: Affiliate & Owner 122:105109 | P9 | todo |
| OWN-009 | Help & support (contact channels from General settings; tickets action added in P9) | `owner.support` | empty state when no channel configured | — (new, QA-P2-007) | new (owner portal design language) | P2 | todo |

## 5. Central website (`{central}/`)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| WEB-001 | Home | `central.home` | scroll animations, auto-scroll strips, `?ref=` tracking, "store does not exist" notice state | website.home | 12:41421, m 12:42786 | P6 | done |
| WEB-002 | Templates (themes gallery) | `central.templates` | filter, preview | website.templates | 12:41985, m 12:43584 | P6 | done |
| WEB-003 | Templates by category | `central.templates.category` | empty | — (Figma) | 12:42161, m 12:43373 | P6 | todo |
| WEB-004 | Theme preview | `central.preview` (`/preview/{slug}`) | country switch, exit | preview (central `/preview/*`) | designed: Central Website additions 127:11426 · mobile view 127:11559 | P7 | todo |
| WEB-005 | Plans / pricing | `central.pricing` | billing term toggle | website.pricing | 12:42011, m 12:43222 | P6 | done |
| WEB-006 | How it works | `central.how-it-works` | | website.how-it-works | 12:41810, m 12:43441 | P6 | done |
| WEB-007 | About | `central.about` | | website.about | 12:41879, m 12:43671 | P6 | done |
| WEB-008 | Contact | `central.contact` | form submit, success, validation | website.contact | 12:41842, m 12:43632 | P6 | done |
| WEB-009 | FAQs | `central.faqs` | accordion | website.faqs | 12:41906, m 12:43700 | P6 | done |
| WEB-010 | Terms | `central.terms` | | website.terms | 12:41891, m 12:43683 | P6 | done |
| WEB-011 | Privacy | `central.privacy` | | website.privacy | designed: Central Website additions 127:11603 · m 127:11737 | P6 | done |
| WEB-012 | Static page | `central.page` | 404 if inactive | website.page | designed: Central Website additions 127:11792 | P6 | todo |
| WEB-013 | Blog list | `central.blog.index` | category filter, paginate, empty | website.blog | designed: Central Website additions 127:11911 · m 127:12095 | P6 | done |
| WEB-014 | Blog post | `central.blog.show` | related, share, JSON-LD | website.blog.show | designed: Central Website additions 127:12209 · m 127:12370 | P6 | done |
| WEB-015 | Newsletter signup (footer) | `central.newsletter.store` | success, duplicate, error | — (A5) | 12:42512 | P6 | done |
| WEB-016 | Sign up (email, phone, plan, coupon, compliance accept) | `central.register` | validation, social auth | website.register | 12:42223, m 12:43467 | P2 | done |
| WEB-016a | ↳ Social auth popup result (Google / Apple) | `central.social.callback` | success, error | website.social.google, website.social.google.callback, website.social.apple, website.social.apple.callback | designed: Central Website additions success 125:10900 · cancelled 125:10912 | P2 | done |
| WEB-016b | ↳ Plan choice step | step | trial / paid | RegisterPage step | designed: Central Website additions 125:10149 · m 125:10421 | P2 | done |
| WEB-016c | ↳ Payment step (gateway, card fields, promo code) | step | coupon error | RegisterPage step | designed: Central Website additions 125:10606 · m 125:10779 (payment methods = old project, labelled box) | P2 | done |
| WEB-017 | Email verification sent / resend | `central.register.verify` | resend, cooldown | RegisterPage createPendingAndSendEmail | designed: Central Website additions 125:10925 · resent 125:11066 · m 125:11200 | P2 | done |
| WEB-018 | Complete registration: account (name, password) | `central.register.complete` | invalid/expired token | website.register.complete | designed: Central Website additions 125:11277 · m 125:11433 | P2 | done |
| WEB-018a | ↳ Step: store (shop name, sub-domain / custom domain + DNS check, profit %, categories) | step | availability check | CompleteRegistrationPage | designed: Central Website additions 125:11537 · validation 125:11701 · custom domain/DNS/profit %/categories 133:12577 · m 125:11865 | P2 | done |
| WEB-019 | Registration payment redirect | `central.register.payment.charge` | redirecting state | website.register.payment.charge | designed: Central Website additions 125:11977 | P2 | done |
| WEB-019a | ↳ Payment success | `central.register.payment.success` | | website.register.payment.success | designed: Central Website additions 125:12081 · m 125:12227 | P2 | done |
| WEB-019b | ↳ Payment failed / cancelled | `central.register.payment.cancel` | retry | website.register.payment.cancel | designed: Central Website additions 125:12321 | P2 | done |
| WEB-020 | Provisioning / loading | `central.register.provisioning` | progress, failure | — (Figma) | 12:42305, m 12:43549 | P2 | done |
| WEB-021 | Onboarding wizard | `central.onboarding` | invalid tenant state | website.store.onboarding | mapped: Vendor Panel (new screens) 82:99932 / 82:99996 / 82:100084 / 82:100161 (runs inside store panel) | P2 | todo |
| WEB-021a | ↳ Step: Countries | step | select all | StoreOnboardingWizard | mapped: 82:99932 (Countries) | P2 | todo |
| WEB-021b | ↳ Step: Languages | step | | StoreOnboardingWizard | mapped: 82:99996 (Languages) | P2 | todo |
| WEB-021c | ↳ Step: Theme | step | | — (new) | mapped: 82:100084 (Theme) | P2 | todo |
| WEB-021d | ↳ Step: Launch | step | catalog sync wait | StoreOnboardingWizard launch | mapped: 82:100161 (Launch) | P2 | todo |
| WEB-022 | Login (store owner) | `central.login` | social, throttle | owner.login | 12:42268, m 12:43512 | P2 | todo |
| WEB-023 | Forgot password | `central.password.request` | sent state | — (new) | designed: Central Website additions 127:12477 · sent 127:12646 · m 127:12586 | P2 | todo |
| WEB-024 | Reset password | `central.password.reset` | invalid token | — (new) | designed: Central Website additions 127:12754 · invalid token 127:12867 | P2 | todo |
| WEB-025 | Language switcher | `central.locale` | | locale.switch | 12:42481 | P6 | done |
| WEB-026 | Layout: navbar, footer, shared components | layout `x-central::layout` | mobile menu | layouts.website | 12:42345, 12:42512, 12:42333, 12:42338, 12:42490, 12:42640, 12:42645, 12:42650, 12:42669, 12:42770, 12:43577, 12:43610, 12:43747, 12:43764, 12:43775 | P6 | done |
| WEB-027 | 404 | fallback | | website.not-found | designed: Central Website additions 127:12974 · m 127:13076 | P6 | done |
| WEB-028 | Maintenance | view | window end time | website.maintenance | designed: Central Website additions 127:13205 | P6 | todo |
| WEB-029 | 500 | view | | — | designed: Central Website additions 127:13129 | P6 | todo |
| WEB-030 | 419 | view | reload | — | designed: Central Website additions 127:13167 | P6 | todo |
| WEB-031 | Alternative landing "NO GRGR" | — | — | — (Figma only) | 12:25861, m 12:26195 | — | dropped (01-modules §D: central site = 12:41054; "NO GRGR" landing not used) |

## 6. Storefront (`{store-domain}/`)

### 6.1 Home variants (13)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-ECO-001 | Home — Ecommet | `store.home` | sections from page builder, load more, tabbed products, flash countdown, empty section hidden | tenant.home, preview, preview.home.tabbed-products, tenant.storefront.home.tabbed-products (themes.ecommet) | 1:1002, m 1:2656 | P7 | todo |
| SF-ECO-001a | ↳ Home mobile alt "Star" | `store.home` | | — (Figma) | m 1:2710 | P7 | todo |
| SF-ELO-001 | Home — Elora v1 (base) | `store.home` | as above + card-fan slider, marquee | tenant.home (elora home) | 1:15432, m 1:21125 | P7 | done |
| SF-ELO-002 | Home — Elora v2 (Theme #1) | `store.home` | | tenant.home (elora home-v2) | 1:26559, m 1:24137 | P7 | done |
| SF-ELO-003 | Home — Elora v3 (Theme #2) | `store.home` | | tenant.home (elora home-v3) | 1:26978, m 1:24477 | P7 | done |
| SF-ELO-004 | Home — Elora v4 (Theme #3) | `store.home` | | tenant.home (elora home-v4) | 1:27464, m 1:24870 | P7 | done |
| SF-ELO-005 | Home — Elora v5 (Theme #4) | `store.home` | | tenant.home (elora home-v5) | 1:28050, m 1:25467 | P7 | done |
| SF-ELO-006 | Home — Elora v6 (Theme #5) | `store.home` | | tenant.home (elora home-v6) | 1:28992, m 1:25994 | P7 | done |
| SF-SQF-001 | Home — Souqify v1 (base) | `store.home` | flash countdown, deals | tenant.home (souqify home) | 1:72835, m 1:77852 | P7 | done |
| SF-SQF-002 | Home — Souqify v2 (Theme #1) | `store.home` | | tenant.home (souqify home-v2) | 1:81585, m 1:78202 | P7 | done |
| SF-SQF-003 | Home — Souqify v3 (Theme #2) | `store.home` | | tenant.home (souqify home-v3) | 1:82288, m 1:78599 | P7 | done |
| SF-SQF-004 | Home — Souqify v4 (Theme #3) | `store.home` | | tenant.home (souqify home-v4) | 1:82796, m 1:78957 | P7 | done |
| SF-SQF-005 | Home — Souqify v5 (Theme #4) | `store.home` | | tenant.home (souqify home-v5) | 1:83157, m 1:79266 | P7 | done |
| SF-SQF-006 | Home — Souqify v6 (Theme #5) | `store.home` | | tenant.home (souqify home-v6) | 1:83936, m 1:79691 | P7 | done |
| SF-SQF-007 | Home — Souqify alternate | — | — | — (Figma) | 1:73212 | — | post-launch (01-modules §D: seasonal skin, not part of the 13) |
| SF-SQF-008 | Home — Souqify Christmas (snow) | — | — | — (Figma) | 1:73614 | — | post-launch (01-modules §D: seasonal skin, not part of the 13) |

### 6.2 Ecommet shared pages

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-ECO-010 | Layout: header, nav, locale switcher (lang/currency/country), mobile menu, footer, cards | layout | | themes.ecommet.layout | 1:348, m 1:2764, boards 1:4259, 1:4261, 1:4263, 1:4265 | P7 | todo |
| SF-ECO-011 | Search autocomplete dropdown (recent searches) | `store.search.autocomplete` | no results | tenant.storefront.search.autocomplete, preview.search.autocomplete | 1:1163 | P7 | todo |
| SF-ECO-012 | Image search modal | `store.search.image` | upload, quota error | tenant.storefront.search.image | designed: Storefront additions 135:111164 · results 135:111194 · m 135:111252 | P10 | todo |
| SF-ECO-013 | Search results | `store.search` | empty | tenant.storefront.search | reuse 1:1191, unchanged · variant notes 137:113051 | P7 | todo |
| SF-ECO-014 | Best selling (period tabs) | `store.best-selling` | load more | tenant.storefront.best-selling | 1:1191 | P7 | todo |
| SF-ECO-015 | New in | `store.new-in` | | tenant.storefront.new-in | reuse 1:1191, unchanged · variant notes 137:113051 | P7 | todo |
| SF-ECO-016 | Top rated (full star) | `store.top-rated` | | tenant.storefront.full-star | reuse 1:1191, unchanged · variant notes 137:113051 | P7 | todo |
| SF-ECO-017 | Offers | `store.offers` | | tenant.storefront.offers | reuse 1:1191, unchanged · variant notes 137:113051 | P7 | todo |
| SF-ECO-018 | Category listing | `store.category` | sort, load more, empty | tenant.storefront.category, tenant.storefront.category.products.json, tenant.storefront.products.json, preview.category.products.json, preview.products.json | 1:1693, m 1:2979 | P7 | todo |
| SF-ECO-018a | ↳ Filter panel (price, variations, rating, availability) | panel | apply / clear | CategoryPage | 1:1796 | P7 | todo |
| SF-ECO-019 | Product details | `store.product` | variant select, qty, add to cart, flash countdown, delivery estimate, 404 by country | tenant.storefront.product | 1:3380, m 1:3073 | P7 | todo |
| SF-ECO-019a | ↳ Reviews modal | modal | load more | ProductPage openReviewsModal | designed: Storefront additions 135:111287 · write 135:111423 · m 135:111460 | P7 | todo |
| SF-ECO-019b | ↳ Delivery estimate popup | modal | | — (A3 popup days) | designed: Storefront additions 137:111644 · m 137:111815 | P7 | todo |
| SF-ECO-019c | ↳ Free-shipping popup | modal | | — (Figma) | 1:2646 | P7 | todo |
| SF-ECO-020 | Item added to cart popup | modal | | CartManager | 1:4267 | P7 | todo |
| SF-ECO-021 | Mini cart | dropdown | | CartDropdown | 1:1662 | P7 | todo |
| SF-ECO-022 | Cart | `store.cart` | qty, remove, coupon, free-shipping progress | tenant.storefront.cart | 1:1348, m 1:3210 | P7 | todo |
| SF-ECO-022a | ↳ Cart empty | state | | CartPage | 1:1240, m 1:3329 | P7 | todo |
| SF-ECO-023 | Checkout | `store.checkout` | address, shipping estimate, payment method, coupon, place order, stock/country errors | tenant.storefront.checkout | 1:1520, m 1:3540 · Storefront additions: 98:100243 + states 98:100522, m 98:100610 + m states 98:100707 | P7 | todo |
| SF-ECO-023a | ↳ Select / add address modal | modal | | CheckoutPage | Storefront additions: address modal 97:100527 (8 states), m sheets 97:102080–97:102871 | P7 | todo |
| SF-ECO-023b | ↳ Payment redirect / cancelled | `store.payment.charge` / `.cancel` | retry | tenant.payment.charge, tenant.payment.cancel, tenant.payment.success, tenant.payment.webhook | designed: redirect 114:108887 / m 114:109089 · not completed 114:108918 / m 114:109120 · toasts 114:109173 / m 114:109243 · Stripe = inline Stripe.js card on checkout / pay page, verified server side, no webhook (decision #78 b); PayPal = redirect | P7 | todo |
| SF-ECO-024 | Order success / status | `store.orders.status` | invoice link, reorder | tenant.storefront.order-status | designed: Storefront additions 137:112775 · m 137:112987 | P7 | todo |
| SF-ECO-025 | Login / register | `store.auth.login` | tabs, Google / Apple | tenant.storefront.login, tenant.storefront.social.google, tenant.storefront.social.google.callback, tenant.storefront.social.apple, tenant.storefront.social.apple.callback | Storefront additions: 99:100863, m 99:101342 / 99:101406 | P7 | todo |
| SF-ECO-025a | ↳ Forgot password | `store.auth.password.request` | | — (new) | Storefront additions: 99:101145, m 99:101485 | P7 | todo |
| SF-ECO-025b | ↳ Reset password | `store.auth.password.reset` | | — (new) | Storefront additions: 99:101145, m 99:101485 | P7 | todo |
| SF-ECO-025c | ↳ Verify email | `store.auth.verify` | | — (new) | designed: Storefront additions 114:107621 / m 114:107802 | P7 | todo |
| SF-ECO-026 | Account home / menu | `store.account` | | tenant.storefront.profile | 1:1833, m 1:3670 · Storefront additions: profile 101:103157, m 101:103445 | P7 | todo |
| SF-ECO-026a | ↳ Personal info edit | tab | | ProfilePage | m 1:3848 · Storefront additions: 101:103157, m 101:103445 | P7 | todo |
| SF-ECO-026b | ↳ Addresses (CRUD modal) | tab | default | ProfilePage | 1:1833 · Storefront additions: 101:103573, m 101:103845 | P7 | todo |
| SF-ECO-027 | Orders: All | `store.orders.index` | search, empty | ProfilePage orders | 1:1871, m 1:3725 · Storefront additions: chips+search+empty 100:101877, m 100:102522 | P7 | todo |
| SF-ECO-027a | ↳ Pending | tab | | ProfilePage | 1:2353, m 1:3815 | P7 | todo |
| SF-ECO-027b | ↳ Processing | tab | | ProfilePage | 1:2384, m 1:3933 | P7 | todo |
| SF-ECO-027c | ↳ Shipped | tab | | ProfilePage | 1:4110, m 1:3987 | P7 | todo |
| SF-ECO-027d | ↳ Delivered | tab | | ProfilePage | 1:2518, m 1:4047 | P7 | todo |
| SF-ECO-027e | ↳ Canceled | tab | | ProfilePage | 1:2615, m 1:4226 | P7 | todo |
| SF-ECO-028 | Order details | `store.orders.show` | reorder, invoice | tenant.storefront.order-status | 1:2241 · Storefront additions: 100:102212, m 100:102674 | P7 | todo |
| SF-ECO-028a | ↳ State: shipped | state | | OrderStatusPage | 1:2126 | P7 | todo |
| SF-ECO-028b | ↳ State: delivered | state | | OrderStatusPage | 1:2014 | P7 | todo |
| SF-ECO-028c | ↳ Cancel order modal (2-step) | modal | | ManagesOrderCancellation | Storefront additions: modals 103:107273, m sheets 103:107488 | P7 | todo |
| SF-ECO-028d | ↳ Write review modal | modal | | OrderStatusPage review | Storefront additions: modals 103:107273, m sheets 103:107488 | P7 | todo |
| SF-ECO-029 | Order tracking | `store.orders.tracking` | timeline | tenant.storefront.order-tracking | Storefront additions: tracking panel in 100:102212, m 100:102674 | P7 | todo |
| SF-ECO-030 | Request return | `store.returns.create` | photos/video upload limits | tenant.storefront.order-return | Storefront additions: 102:105455, m 102:105748 | P7 | todo |
| SF-ECO-031 | Returns list | `store.returns.index` | empty | ProfilePage returns | Storefront additions: 102:104664, m 102:104937 | P7 | todo |
| SF-ECO-032 | Return detail (thread, reply, withdraw) | `store.returns.show` | | tenant.storefront.return-detail | Storefront additions: 102:105046, m 102:105336 | P7 | todo |
| SF-ECO-033 | Favorites | `store.favorites` | toggle, empty | tenant.storefront.favorites, tenant.storefront.favorites.ids, tenant.storefront.favorites.list, tenant.storefront.favorites.toggle | Storefront additions: wishlist 101:103953, m 101:104234 | P7 | todo |
| SF-ECO-034 | CMS page | `store.page` | | tenant.storefront.page | designed: Storefront additions 114:107865 / m 114:108055 | P7 | todo |
| SF-ECO-035 | Support request form / newsletter signup | component | success, error | SupportRequestForm | designed: Storefront additions 114:108109 / m 114:108353 (form + newsletter + success toast) | P7 | todo |
| SF-ECO-036 | 404 (themed) | `store.not-found` | | tenant.storefront.not-found, tenant.storefront.404 | designed: Storefront additions 114:108479 / m 114:108666 | P7 | todo |

### 6.3 Elora shared pages

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-ELO-010 | Layout: navbar (6 header variants), footer, locale switcher, mobile nav | layout | | themes.elora.layout, partials/header-v* | 1:20866 | P7 | done |
| SF-ELO-011 | Components: cards, inputs, sale tags, marquee, card-fan, swatches (36) | components | | themes.elora | 1:18492, 1:20559, 1:20567, 1:20600, 1:20631, 1:20764, 1:20802, 1:22460, 1:22593, 1:22714, 1:22747, 1:22760, 1:23902, 1:23921, 1:23938, 1:23953, 1:24018, 1:24039, 1:24104, 1:25363, 1:25364, 1:25458, 1:25459, 1:25465, 1:25466, 1:29865, 1:30232, 1:26485–1:26557 | P7 | done |
| SF-ELO-012 | Search autocomplete | `store.search.autocomplete` | | tenant.storefront.search.autocomplete | designed: Storefront additions 135:111544 · m 135:111756 | P7 | done |
| SF-ELO-013 | Image search modal | `store.search.image` | | tenant.storefront.search.image | designed: Storefront additions 135:111805 · results 135:111835 · m 135:111893 | P10 | todo |
| SF-ELO-014 | Search results | `store.search` | empty | tenant.storefront.search | reuse 1:17172 (m 1:21453), unchanged · variant notes 137:113975 | P7 | done |
| SF-ELO-015 | Best selling | `store.best-selling` | | tenant.storefront.best-selling | reuse 1:17172 (m 1:21453), unchanged · variant notes 137:113975 | P7 | done |
| SF-ELO-016 | New in | `store.new-in` | | tenant.storefront.new-in | reuse 1:17172 (m 1:21453), unchanged · variant notes 137:113975 | P7 | done |
| SF-ELO-017 | Top rated (full star) | `store.top-rated` | | tenant.storefront.full-star | reuse 1:17172 (m 1:21453), unchanged · variant notes 137:113975 | P7 | done |
| SF-ELO-018 | Offers | `store.offers` | | tenant.storefront.offers | reuse 1:17172 (m 1:21453), unchanged · variant notes 137:113975 | P7 | done |
| SF-ELO-019 | Product listing / category | `store.category` | sort, filter, load more, empty | tenant.storefront.category | 1:17172, m 1:21453 | P7 | done |
| SF-ELO-019a | ↳ Filter sheet (mobile) | modal | | CategoryPage | m 1:22799 | P7 | done |
| SF-ELO-019b | ↳ Sort-by popup (mobile) | modal | | CategoryPage | m 1:23024 | P7 | done |
| SF-ELO-020 | Product details | `store.product` | | tenant.storefront.product | 1:16245, m 1:21580 | P7 | done |
| SF-ELO-020a | ↳ Product details (no recommendations variant) | state | | ProductPage | 1:16088 | P7 | done |
| SF-ELO-020b | ↳ Delivery popup | modal | | — (A3 popup days) | m 1:23053 | P7 | done |
| SF-ELO-020c | ↳ Privacy & secure payment popup | modal | | — (Figma) | m 1:23152 | P7 | done |
| SF-ELO-020d | ↳ Reviews modal | modal | | ProductPage | designed: Storefront additions 135:111928 · write 135:112064 · m 135:112101 | P7 | done |
| SF-ELO-021 | Mini cart / item added | dropdown | | CartDropdown | designed: Storefront additions 137:112132 · m 137:112323 | P7 | done |
| SF-ELO-022 | Cart | `store.cart` | | tenant.storefront.cart | 1:17541, m 1:22029 | P7 | done |
| SF-ELO-022a | ↳ Cart empty | state | | CartPage | m 1:23315 | P7 | done |
| SF-ELO-023 | Checkout | `store.checkout` | | tenant.storefront.checkout | 1:18245, m 1:22186 · Storefront additions: 98:100761 + states 98:101058, m 98:101146 + m states 98:101243 | P7 | done |
| SF-ELO-023a | ↳ Step: shipping information | step | | CheckoutPage | 1:18002 | P7 | done |
| SF-ELO-023b | ↳ Step: payment details (card) | step | | CheckoutPage | 1:17863 | P7 | done |
| SF-ELO-023c | ↳ Add address modal | modal | | CheckoutPage | 1:20817, m 1:22912 · Storefront additions: canonical 97:101294, m sheets 97:102977–97:103768 | P7 | done |
| SF-ELO-023d | ↳ Select address modal | modal | | CheckoutPage | 1:21111, m 1:22988 · Storefront additions: checkout list 98:100761, m 98:101146 | P7 | done |
| SF-ELO-023e | ↳ Payment redirect / cancelled | `store.payment.charge` / `.cancel` | | tenant.payment.charge, .cancel | designed: redirect 115:110169 / m 115:110374 · not completed 115:110200 / m 115:110405 · toasts 115:110460 / m 115:110530 | P7 | done |
| SF-ELO-024 | Order success | `store.orders.status` | | tenant.storefront.order-status | m 1:23007 | P7 | done |
| SF-ELO-025 | Login / register | `store.auth.login` | | tenant.storefront.login | Storefront additions: 99:101528, m 99:102013 / 99:102077 | P7 | done |
| SF-ELO-025a | ↳ Forgot password | `store.auth.password.request` | | — (new) | Storefront additions: 99:101813, m 99:102156 | P7 | done |
| SF-ELO-025b | ↳ Reset password | `store.auth.password.reset` | | — (new) | Storefront additions: 99:101813, m 99:102156 | P7 | done |
| SF-ELO-025c | ↳ Verify email | `store.auth.verify` | | — (new) | designed: Storefront additions 115:108883 / m 115:109067 | P7 | done |
| SF-ELO-026 | Account / profile (info, addresses) | `store.account` | | tenant.storefront.profile | Storefront additions: profile 101:104351, m 101:104642; addresses 101:104772, m 101:105047 | P7 | done |
| SF-ELO-027 | Favorites | `store.favorites` | | tenant.storefront.favorites | 1:18588, m 1:23220 | P7 | done |
| SF-ELO-027a | ↳ Favorites empty | state | | FavoritesPage | m 1:23277 | P7 | done |
| SF-ELO-028 | Orders | `store.orders.index` | track, reorder, review | ProfilePage orders | 1:18739, m 1:23374 · Storefront additions: 100:102812, m 100:103463 | P7 | done |
| SF-ELO-028a | ↳ Orders empty | state | | ProfilePage | 1:19347, m 1:23542 | P7 | done |
| SF-ELO-029 | Order details (download invoice) | `store.orders.show` | | tenant.storefront.order-status | 1:18987, m 1:23600 · Storefront additions: 100:103150, m 100:103617 | P7 | done |
| SF-ELO-029a | ↳ Cancel order modal | modal | | ManagesOrderCancellation | Storefront additions: modals 103:108307, m sheets 103:108522 | P7 | done |
| SF-ELO-029b | ↳ Write review modal | modal | | OrderStatusPage | Storefront additions: modals 103:108307, m sheets 103:108522 | P7 | done |
| SF-ELO-030 | Order tracking | `store.orders.tracking` | | tenant.storefront.order-tracking | 1:19981, m 1:23708 | P7 | done |
| SF-ELO-030a | ↳ Live tracking | state | | — (Figma) | m 1:20213 | P7 | done |
| SF-ELO-031 | Request return | `store.returns.create` | | tenant.storefront.order-return | Storefront additions: 102:106680, m 102:106976 | P7 | done |
| SF-ELO-032 | Returns list | `store.returns.index` | | ProfilePage returns | Storefront additions: 102:105879, m 102:106155 | P7 | done |
| SF-ELO-033 | Return detail | `store.returns.show` | | tenant.storefront.return-detail | Storefront additions: 102:106266, m 102:106559 | P7 | done |
| SF-ELO-034 | CMS page | `store.page` | | tenant.storefront.page | designed: Storefront additions 115:109132 / m 115:109325 | P7 | done |
| SF-ELO-035 | Support request form / newsletter signup | component | | SupportRequestForm | designed: Storefront additions 115:109381 / m 115:109628 (form + newsletter + success toast) | P7 | done |
| SF-ELO-036 | 404 (themed) | `store.not-found` | | tenant.storefront.not-found | designed: Storefront additions 115:109756 / m 115:109946 | P7 | done |

### 6.4 Souqify shared pages

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-SQF-010 | Layout: navbar, footer (fix Elora logo), mobile nav bar | layout | | themes.souqify.layout | 1:81293, 1:81428, m 1:80539 | P7 | done |
| SF-SQF-011 | Components: buttons, product cards, side cards, deal card, labels, discount, swatches (36) | components | | themes.souqify | 1:74471, 1:74473, 1:74476, 1:74550, 1:76154, 1:76210, 1:76304, 1:80047, 1:80075, 1:80140, 1:80147, 1:80975, 1:81011, 1:81148, 1:82791, 1:81511–1:81583 | P7 | done |
| SF-SQF-012 | Search autocomplete | `store.search.autocomplete` | | tenant.storefront.search.autocomplete | designed: Storefront additions 135:112185 · m(ar) 135:112339 | P7 | done |
| SF-SQF-013 | Image search modal | `store.search.image` | | tenant.storefront.search.image | designed: Storefront additions 135:112388 · results 135:112418 · m(ar) 135:112476 | P10 | todo |
| SF-SQF-014 | Search results | `store.search` | | tenant.storefront.search | reuse 1:75532 (m 1:76311), unchanged · variant notes 137:114609 | P7 | done |
| SF-SQF-015 | Best selling | `store.best-selling` | | tenant.storefront.best-selling | reuse 1:75532 (m 1:76311), unchanged · variant notes 137:114609 | P7 | done |
| SF-SQF-016 | New in | `store.new-in` | | tenant.storefront.new-in | reuse 1:75532 (m 1:76311), unchanged · variant notes 137:114609 | P7 | done |
| SF-SQF-017 | Top rated (full star) | `store.top-rated` | | tenant.storefront.full-star | reuse 1:75532 (m 1:76311), unchanged · variant notes 137:114609 | P7 | done |
| SF-SQF-018 | Offers | `store.offers` | | tenant.storefront.offers | m 1:77354 | P7 | done |
| SF-SQF-019 | Category listing (filters) | `store.category` | | tenant.storefront.category | 1:75532, m 1:76311 | P7 | done |
| SF-SQF-020 | Product details | `store.product` | | tenant.storefront.product | 1:74583, m 1:77427 | P7 | done |
| SF-SQF-020a | ↳ Reviews modal | modal | | ProductPage | designed: Storefront additions 135:112511 · write 135:112647 · m(ar) 135:112684 | P7 | done |
| SF-SQF-020b | ↳ Delivery estimate popup | modal | | — (A3) | designed: Storefront additions 137:111894 · m(ar) 137:112053 (based on Elora 1:23053) | P7 | done |
| SF-SQF-021 | Mini cart / item added | dropdown | | CartDropdown | designed: Storefront additions 137:112371 · m(ar) 137:112504 | P7 | done |
| SF-SQF-022 | Cart | `store.cart` | | tenant.storefront.cart | 1:75039, m 1:76392 | P7 | done |
| SF-SQF-022a | ↳ Cart empty | state | | CartPage | designed: Storefront additions 137:112552 · m(ar) 137:112713 | P7 | done |
| SF-SQF-023 | Checkout | `store.checkout` | | tenant.storefront.checkout | 1:75299, m 1:76569 · Storefront additions: 98:101297 + states 98:101571, m 98:101659 + m states 98:101756 | P7 | done |
| SF-SQF-023a | ↳ Add address modal | modal | | CheckoutPage | 1:76266, m 1:80427 · Storefront additions: canonical 97:102061, m sheets 97:103874–97:104665 | P7 | done |
| SF-SQF-023b | ↳ Select address modal | modal | | CheckoutPage | m 1:80503 · Storefront additions: checkout list 98:101297, m 98:101659 | P7 | done |
| SF-SQF-023c | ↳ Payment redirect / cancelled | `store.payment.charge` / `.cancel` | | tenant.payment.charge, .cancel | designed: redirect 115:112186 / m(ar) 115:112366 · not completed 115:112217 / m(ar) 115:112397 · toasts 115:112455 / m 115:112525 | P7 | done |
| SF-SQF-024 | Order success | `store.orders.status` | | tenant.storefront.order-status | m 1:80522 | P7 | done |
| SF-SQF-025 | Login / register | `store.auth.login` | | tenant.storefront.login | Storefront additions: 99:102199, m 99:102634 / 99:102698 | P7 | done |
| SF-SQF-025a | ↳ Forgot password | `store.auth.password.request` | | — (new) | Storefront additions: 99:102459, m 99:102777 | P7 | done |
| SF-SQF-025b | ↳ Reset password | `store.auth.password.reset` | | — (new) | Storefront additions: 99:102459, m 99:102777 | P7 | done |
| SF-SQF-025c | ↳ Verify email | `store.auth.verify` | | — (new) | designed: Storefront additions 115:110988 / m(ar) 115:111147 | P7 | done |
| SF-SQF-026 | Account / profile (info, addresses) | `store.account` | | tenant.storefront.profile | Storefront additions: profile 101:105157, m 101:105423; addresses 101:105556, m 101:105806 | P7 | done |
| SF-SQF-027 | Favorites | `store.favorites` | | tenant.storefront.favorites | 1:75718, m 1:76864 | P7 | done |
| SF-SQF-028 | Orders | `store.orders.index` | | ProfilePage orders | 1:75734, m 1:77130 · Storefront additions: 100:103757, m 100:104358 | P7 | done |
| SF-SQF-028a | ↳ Orders empty | state | | ProfilePage | 1:76132, m 1:77298 | P7 | done |
| SF-SQF-029 | Order details | `store.orders.show` | | tenant.storefront.order-status | 1:75862, m 1:80745 · Storefront additions: 100:104070, m 100:104524 | P7 | done |
| SF-SQF-029a | ↳ Cancel order modal | modal | | ManagesOrderCancellation | Storefront additions: modals 103:109294, m sheets 103:109509 | P7 | done |
| SF-SQF-029b | ↳ Write review modal | modal | | OrderStatusPage | Storefront additions: modals 103:109294, m sheets 103:109509 | P7 | done |
| SF-SQF-030 | Order tracking | `store.orders.tracking` | | tenant.storefront.order-tracking | 1:75976, m 1:80856 | P7 | done |
| SF-SQF-030a | ↳ Live tracking | state | | — (Figma) | m 1:80997 | P7 | done |
| SF-SQF-031 | Request return | `store.returns.create` | | tenant.storefront.order-return | Storefront additions: 102:107866, m 102:108137 | P7 | done |
| SF-SQF-032 | Returns list | `store.returns.index` | | ProfilePage returns | Storefront additions: 102:107109, m 102:107360 | P7 | done |
| SF-SQF-033 | Return detail | `store.returns.show` | | tenant.storefront.return-detail | Storefront additions: 102:107474, m 102:107742 | P7 | done |
| SF-SQF-034 | CMS page | `store.page` | | tenant.storefront.page | designed: Storefront additions 115:111215 / m(ar) 115:111383 | P7 | done |
| SF-SQF-035 | Support request form / newsletter signup | component | | SupportRequestForm | designed: Storefront additions 115:111442 / m(ar) 115:111664 (form + newsletter + success toast) | P7 | done |
| SF-SQF-036 | 404 (themed) | `store.not-found` | | tenant.storefront.not-found | designed: Storefront additions 115:111795 / m(ar) 115:111960 | P7 | done |

### 6.5 Storefront system pages (all families, not themed)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-ALL-001 | Store not launched | view `errors.store-not-launched` | admin login link | errors.store-not-launched | designed: ECO 114:108727 / m 114:108755 · ELO 115:110009 / m 115:110037 · SQF 115:112026 / m(ar) 115:112054 | P7 | todo |
| SF-ALL-002 | Maintenance | view `errors.store-maintenance` | | CheckMaintenanceWindow | designed: ECO 114:108783 / m 114:108812 · ELO 115:110065 / m 115:110094 · SQF 115:112082 / m(ar) 115:112111 | P7 | todo |
| SF-ALL-003 | Store suspended / gateway blocked | view | | CheckTenantGatewayBlocked | designed: suspended ECO 114:108841 / m 114:108864 · ELO 115:110123 / m 115:110146 · SQF 115:112140 / m(ar) 115:112163 · gateway blocked = "Online payment unavailable" toast in each toast board | P7 | todo |
| SF-ALL-004 | Error 500 / 419 / 403 | views | | — | designed: 500 ECO 114:109313 / m 114:109337 · ELO 115:110600 / m 115:110624 · SQF 115:112595 / m(ar) 115:112619 · 419 ECO 114:109361 · ELO 115:110648 · SQF 115:112643 · 403 ECO 114:109385 · ELO 115:110672 · SQF 115:112667 | P7 | todo |
| SF-ALL-005 | App landing ("Open in app / Install") | `store.app` | store badges | tenant.app-landing | designed: ECO 114:109409 / m 114:109612 · ELO 115:110696 / m 115:110902 · SQF 115:112691 / m(ar) 115:112872 | P11 | todo |
| SF-ALL-006 | Custom Twig theme render (all page templates) | engine | fallback to family theme on render error | UploadedBladeTemplateStrategy | — (vendor-authored) | P7 | todo |

### 6.6 Path storefront `/vendor/{slug}` (old)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| SF-PATH-001 | Path home | — | — | tenant.path.home | — | — | dropped (01-modules §D: path storefront dropped; replaced by `{central}/preview/{slug}` → WEB-004) |
| SF-PATH-002 | Path login | — | — | tenant.path.storefront.login | — | — | dropped (same) |
| SF-PATH-003 | Path best selling / search | — | — | tenant.path.storefront.best-selling, tenant.path.storefront.search, tenant.path.storefront.search.autocomplete, tenant.path.storefront.products.json, tenant.path.storefront.category.products.json, tenant.path.storefront.home.tabbed-products | — | — | dropped (same) |
| SF-PATH-004 | Path new in / full star / offers | — | — | tenant.path.storefront.new-in, tenant.path.storefront.full-star, tenant.path.storefront.offers | — | — | dropped (same) |
| SF-PATH-005 | Path category | — | — | tenant.path.storefront.category | — | — | dropped (same) |
| SF-PATH-006 | Path product | — | — | tenant.path.storefront.product | — | — | dropped (same) |
| SF-PATH-007 | Path cart / checkout / payment | — | — | tenant.path.storefront.cart, tenant.path.storefront.checkout, tenant.path.payment.charge, tenant.path.payment.cancel, tenant.path.payment.success | — | — | dropped (same) |
| SF-PATH-008 | Path favorites / profile | — | — | tenant.path.storefront.favorites, tenant.path.storefront.profile | — | — | dropped (same) |
| SF-PATH-009 | Path order status / tracking / invoice | — | — | tenant.path.storefront.order-status, tenant.path.storefront.order-tracking, tenant.path.storefront.order-invoice, tenant.path.robots, tenant.path.sitemap | — | — | dropped (same) |
| SF-PATH-010 | Path CMS page | — | — | tenant.path.storefront.page | — | — | dropped (same) |
| SF-PATH-011 | Path `/admin*` redirect | — | — | tenant.path.admin.redirect | — | — | dropped (same) |

## 7. Emails & printable docs

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| MAIL-001 | Email layout (central brand / tenant brand, ar/en, RTL) | `mail::layout` | dark-mode safe, plain-text alt | HtmlTemplateMail | designed: Emails & printables spec 129:10149 · central en 129:10162 · tenant en 129:10215 · tenant ar 129:10286 · central ar 129:10357 | P2 | todo |
| MAIL-002 | Registration complete link (verify email) | `central.registration_complete_link` | | CentralRegistrationCompleteLink | designed: Emails & printables 130:10175 | P2 | todo |
| MAIL-003 | Tenant welcome | `central.tenant_welcome` | | CentralTenantWelcome | designed: Emails & printables 130:10213 | P2 | todo |
| MAIL-004 | Password reset (admin / vendor / affiliate / owner / customer) | `auth.password_reset` | | — (new) | designed: Emails & printables 130:10266 · customer 130:10304 | P2 | todo |
| MAIL-005 | Customer email verification | `store.verify_email` | | — (new) | designed: Emails & printables 130:10343 | P7 | todo |
| MAIL-006 | Subscription activated (tenant) | `tenant.subscription_activated` | | TenantSubscriptionActivated | designed: Emails & printables 130:10382 | P2 | todo |
| MAIL-007 | Subscription renewal reminder (tenant) | `tenant.subscription_renewal_reminder` | | TenantSubscriptionRenewalReminder | designed: Emails & printables 130:10436 | P2 | todo |
| MAIL-008 | Admin: tenant subscription activated | `admin.tenant_subscription_activated` | | AdminTenantSubscriptionActivated | designed: Emails & printables 130:10486 | P2 | todo |
| MAIL-009 | Admin: tenant renewal reminder | `admin.tenant_subscription_renewal_reminder` | | AdminTenantSubscriptionRenewalReminder | designed: Emails & printables 130:10538 | P2 | todo |
| MAIL-010 | Admin: tenant subscription cancelled | `admin.tenant_subscription_cancelled` | | AdminTenantSubscriptionCancelled | designed: Emails & printables 130:10582 | P2 | todo |
| MAIL-011 | Admin: invoice issued | `admin.invoice_issued` | | AdminInvoiceIssued | designed: Emails & printables 130:10635 | P2 | todo |
| MAIL-012 | Order confirmation (customer) | `tenant.order_confirmation` | | TenantOrderConfirmation | designed: Emails & printables 130:10687 | P5 | todo |
| MAIL-013 | Order processing | `tenant.order_processing` | | TenantOrderProcessing | designed: Emails & printables 130:10758 | P5 | todo |
| MAIL-014 | Shipping update (tracking link, estimate) | `tenant.shipping_update` | | TenantShippingUpdate | designed: Emails & printables 130:10820 | P5 | todo |
| MAIL-015 | Order delivered (review CTA) | `tenant.order_delivered` | | TenantOrderDelivered | designed: Emails & printables 130:10889 | P5 | partial |
| MAIL-016 | Order cancelled | `tenant.order_cancelled` | | TenantOrderCancelled | designed: Emails & printables 130:10968 | P5 | todo |
| MAIL-017 | Payment receipt | `tenant.payment_receipt` | | TenantPaymentReceipt | designed: Emails & printables 130:11011 | P5 | todo |
| MAIL-018 | Refund processed | `tenant.refund_processed` | | TenantRefundProcessed | designed: Emails & printables 130:11069 | P5 | todo |
| MAIL-019 | Return update | `tenant.return_update` | | TenantReturnUpdate | designed: Emails & printables 130:11122 | P5 | todo |
| MAIL-020 | Order invoice (attached PDF) | `tenant.order_invoice` | | OrderInvoiceMail | designed: Emails & printables 130:11180 | P5 | todo |
| MAIL-021 | Admin: new order alert | `admin.order_alert` | | AdminOrderAlert | designed: Emails & printables 131:10338 | P5 | todo |
| MAIL-022 | Admin: shipping escalation | `admin.shipping_escalation` | | AdminShippingEscalation | designed: Emails & printables 131:10386 | P5 | todo |
| MAIL-023 | Admin: gateway limit warning | `admin.gateway_limit_warning` | | AdminGatewayLimitWarning | designed: Emails & printables 131:10440 | P5 | todo |
| MAIL-024 | Admin: gateway limit block | `admin.gateway_limit_block` | | AdminGatewayLimitBlock | designed: Emails & printables 131:10494 | P5 | todo |
| MAIL-025 | Wallet transaction receipt (tenant) | `tenant.wallet_transaction_receipt` | | TenantWalletTransactionReceipt | designed: Emails & printables 131:10531 | P5 | todo |
| MAIL-026 | Admin: vendor wallet transaction | `admin.vendor_wallet_transaction` | | AdminVendorWalletTransaction | designed: Emails & printables 131:10579 | P5 | todo |
| MAIL-027 | Vendor settlement receipt (vendor) | `tenant.settlement_receipt` | | VendorSettlementMail | designed: Emails & printables 131:10631 | P5 | todo |
| MAIL-028 | Vendor settlement received (admin) | `admin.settlement_received` | | VendorSettlementAdminMail | designed: Emails & printables 131:10692 | P5 | todo |
| MAIL-029 | Tenant payout sent | `tenant.payout_sent` | | TenantPayoutMail | designed: Emails & printables 131:10733 | P5 | todo |
| MAIL-030 | Request update: product / manufacturing / brand (message, status, payment request) | `tenant.request_update` | | — (new, notification) | designed: Emails & printables 131:10786 | P3 | todo |
| MAIL-031 | Edit request approved / rejected | `tenant.edit_request_reviewed` | | — (new) | designed: Emails & printables 131:10833 | P3 | todo |
| MAIL-032 | Domain connected / DNS failed | `tenant.domain_status` | | — (new) | designed: Emails & printables 131:10875 | P2 | todo |
| MAIL-033 | Compliance reviewed | `tenant.compliance_reviewed` | | — (new) | designed: Emails & printables 131:10930 | P8 | todo |
| MAIL-034 | Custom theme approved / rejected | `tenant.theme_reviewed` | | — (new) | designed: Emails & printables 131:10978 | P7 | todo |
| MAIL-035 | Language / AI add-on purchase receipt | `tenant.addon_receipt` | | — (new) | designed: Emails & printables 131:11023 | P2 | todo |
| MAIL-036 | AI job finished / failed (store translation, catalog) | `tenant.ai_job_status` | | — (new, A6c) | designed: Emails & printables 131:11071 | P10 | todo |
| MAIL-037 | Support ticket reply (admin ↔ vendor) | `support.ticket_reply` | | — (new) | designed: Emails & printables 131:11125 | P9 | todo |
| MAIL-038 | Affiliate: account activated | `affiliate.activated` | | — (new, §C) | designed: Emails & printables 131:11159 | P9 | todo |
| MAIL-039 | Affiliate: conversion approved / reversed | `affiliate.conversion` | | — (new, §C) | designed: Emails & printables 131:11207 | P9 | todo |
| MAIL-040 | Affiliate: payout paid / rejected | `affiliate.payout` | | — (new, §C) | designed: Emails & printables 131:11262 | P9 | todo |
| MAIL-041 | Contact form → admin | `admin.contact_message` | | website.contact | designed: Emails & printables 131:11316 | P6 | todo |
| MAIL-042 | Customer welcome (storefront register) | `store.welcome` | | — (new) | designed: Emails & printables 131:11367 | P7 | todo |
| MAIL-043 | Printable: customer invoice | `store.orders.invoice` | print, PDF, ar/en | tenant.storefront.order-invoice | designed: Emails & printables 132:10518 | P5 | todo |
| MAIL-044 | Printable: order receipt (admin / vendor) | `admin.sales.orders.receipt`, `vendor.sales.orders.receipt` | print | admin.orders.receipt | designed: Emails & printables 132:10888 | P5 | todo |
| MAIL-045 | Printable: shipping label (barcode, company) | `admin.sales.orders.label`, `vendor.sales.orders.label` | print, A6/A4 | admin.orders.shipping-label | designed: Emails & printables 132:11044 | P5 | todo |
| MAIL-046 | Printable: subscription / platform invoice | `admin.finance.invoices.pdf`, `vendor.finance.invoices.pdf` | PDF | admin.invoices.show, tenant.finance.billing.detail | designed: Emails & printables 132:11403 | P2 | todo |
| MAIL-047 | Printable: payout / settlement receipt | `vendor.finance.payouts.pdf` | PDF | tenant_payouts, vendor_settlements | designed: Emails & printables 132:11491 | P5 | todo |

## 8. Mobile app screens (Flutter, white-label, 13 theme skins)

| ID | Page / screen | Route (new, proposed name) | Key actions & states | Old source | Figma node | Phase | Status |
|---|---|---|---|---|---|---|---|
| APP-001 | Splash / bootstrap | `/splash` | offline, tenant resolve fail | mobile.bootstrap, mobile.resolve | design needed | P11 | todo |
| APP-002 | Force update / maintenance / store unavailable | `/blocked` | | mobile.bootstrap | design needed | P11 | todo |
| APP-003 | First-run: country, language, currency | `/preferences/setup` | | mobile.preferences.options | design needed | P11 | todo |
| APP-004 | Home — Ecommet skin | `/home` | sections, pull to refresh, skeletons | mobile.home.section | design needed (base m 1:2656) | P11 | todo |
| APP-004a | ↳ Home — Elora v1 skin | `/home` | | mobile.home.section | design needed (base m 1:21125) | P11 | todo |
| APP-004b | ↳ Home — Elora v2 skin | `/home` | | mobile.home.section | design needed (base m 1:24137) | P11 | todo |
| APP-004c | ↳ Home — Elora v3 skin | `/home` | | mobile.home.section | design needed (base m 1:24477) | P11 | todo |
| APP-004d | ↳ Home — Elora v4 skin | `/home` | | mobile.home.section | design needed (base m 1:24870) | P11 | todo |
| APP-004e | ↳ Home — Elora v5 skin | `/home` | | mobile.home.section | design needed (base m 1:25467) | P11 | todo |
| APP-004f | ↳ Home — Elora v6 skin | `/home` | | mobile.home.section | design needed (base m 1:25994) | P11 | todo |
| APP-004g | ↳ Home — Souqify v1 skin | `/home` | | mobile.home.section | design needed (base m 1:77852) | P11 | todo |
| APP-004h | ↳ Home — Souqify v2 skin | `/home` | | mobile.home.section | design needed (base m 1:78202) | P11 | todo |
| APP-004i | ↳ Home — Souqify v3 skin | `/home` | | mobile.home.section | design needed (base m 1:78599) | P11 | todo |
| APP-004j | ↳ Home — Souqify v4 skin | `/home` | | mobile.home.section | design needed (base m 1:78957) | P11 | todo |
| APP-004k | ↳ Home — Souqify v5 skin | `/home` | | mobile.home.section | design needed (base m 1:79266) | P11 | todo |
| APP-004l | ↳ Home — Souqify v6 skin | `/home` | | mobile.home.section | design needed (base m 1:79691) | P11 | todo |
| APP-005 | Categories | `/categories` | | mobile.categories.index | design needed | P11 | todo |
| APP-006 | Category products (filter sheet, sort sheet) | `/categories/:slug` | infinite scroll, empty | mobile.categories.show, mobile.products.index | design needed | P11 | todo |
| APP-007 | Product lists (new in, best selling, top rated, offers) | `/lists/:list` | | mobile.lists | design needed | P11 | todo |
| APP-008 | Search + autocomplete | `/search` | recent, no results | mobile.search, mobile.search.autocomplete | design needed | P11 | todo |
| APP-009 | Image search (camera / gallery) | `/search/image` | quota error | — (A6c #9) | design needed | P11 | todo |
| APP-010 | Product details (variants, delivery estimate, related) | `/products/:slug` | unavailable in country | mobile.products.show, .related | design needed | P11 | todo |
| APP-010a | ↳ Reviews list + write review | `/products/:slug/reviews` | | mobile.reviews.index, mobile.reviews.store | design needed | P11 | todo |
| APP-011 | Cart (coupon, free-shipping progress) | `/cart` | empty, stock error | mobile.cart.*, mobile.coupon.* | design needed | P11 | todo |
| APP-012 | Checkout (address, shipping quote, gateway) | `/checkout` | quote errors | mobile.checkout.quote, .gateways, .store | design needed | P11 | todo |
| APP-012a | ↳ Payment web view | `/checkout/pay` | cancel | mobile.orders.pay, mobile.payments.* | design needed | P11 | todo |
| APP-012b | ↳ Payment result (success / failed) | `/checkout/result` | poll status | mobile.orders.payment-status | design needed | P11 | todo |
| APP-013 | Orders | `/orders` | status tabs, empty | mobile.orders.index | design needed | P11 | todo |
| APP-014 | Order details (cancel sheet, reorder, invoice) | `/orders/:uuid` | | mobile.orders.show, .cancel, .reorder, .invoice, .cancellation-reasons | design needed | P11 | todo |
| APP-015 | Order tracking | `/orders/:uuid/tracking` | | mobile.orders.tracking | design needed | P11 | todo |
| APP-016 | Request return | `/orders/:uuid/return` | media upload | mobile.returns.store, .options | design needed | P11 | todo |
| APP-017 | Returns list | `/returns` | empty | mobile.returns.index | design needed | P11 | todo |
| APP-018 | Return detail | `/returns/:id` | | mobile.returns.show | design needed | P11 | todo |
| APP-019 | Favorites | `/favorites` | empty | mobile.favorites.* | design needed | P11 | todo |
| APP-020 | Login (+ Google / Apple) | `/auth/login` | | mobile.auth.login | design needed | P11 | todo |
| APP-021 | Register | `/auth/register` | | mobile.auth.register | design needed | P11 | todo |
| APP-022 | Forgot password | `/auth/forgot` | | mobile.auth.forgot | design needed | P11 | todo |
| APP-023 | Reset password (code) | `/auth/reset` | | mobile.auth.reset | design needed | P11 | todo |
| APP-024 | Account menu | `/account` | guest state | mobile.profile.show | design needed | P11 | todo |
| APP-025 | Edit profile | `/account/profile` | | mobile.profile.update | design needed | P11 | todo |
| APP-026 | Change password | `/account/password` | | mobile.profile.password | design needed | P11 | todo |
| APP-027 | Delete account (confirm) | `/account/delete` | | mobile.auth.account.delete | design needed | P11 | todo |
| APP-028 | Addresses list | `/account/addresses` | default, delete | mobile.addresses.index, .destroy, .default | design needed | P11 | todo |
| APP-028a | ↳ Address add / edit (map, intl phone) | `/account/addresses/edit` | | mobile.addresses.store, .update | design needed | P11 | todo |
| APP-029 | Settings (language, currency, country, push toggles) | `/settings` | | mobile.preferences.options, mobile.device-tokens.* | design needed | P11 | todo |
| APP-030 | Notifications inbox (push history) | `/notifications` | empty | — (new, D10) | design needed | P11 | todo |
| APP-031 | CMS page / menus | `/pages/:slug` | | mobile.pages.show, mobile.menus | design needed | P11 | todo |
| APP-032 | No connection / generic error / empty | component | retry | — | design needed | P11 | todo |

## 9. Summary

| Surface | Pages / screens (rows) | With Figma | Need design | Dropped | Post-launch |
|---|---|---|---|---|---|
| Admin panel | 194 | 0 | 192 | 2 | 0 |
| Vendor panel | 159 | 33 | 125 | 0 | 0 |
| Affiliate panel | 18 | 0 | 18 | 0 | 0 |
| Owner portal | 11 | 0 | 11 | 0 | 0 |
| Central website | 41 | 16 | 25 | 1 | 0 |
| Storefront — Ecommet (home + shared) | 50 | 26 | 24 | 0 | 0 |
| Storefront — Elora (6 homes + shared) | 53 | 30 | 23 | 0 | 0 |
| Storefront — Souqify (6 homes + shared + 2 extra) | 48 | 24 | 24 | 0 | 2 |
| Storefront — system pages | 6 | 0 | 5 | 0 | 0 |
| Storefront — old path storefront | 11 | 0 | 0 | 11 | 0 |
| Emails & printable docs | 47 | 0 | 47 | 0 | 0 |
| Mobile app | 48 | 0 | 48 | 0 | 0 |
| **Total** | **686** | **129** | **542** | **14** | **2** |

Rows = pages plus their modal/tab/state sub-rows. "With Figma" = row mapped to at least one node id (incl. dropped/post-launch rows); every top-level frame in `figma-metadata/*.xml` maps to a row. Old GET routes not listed by name are DataTables `*.data` JSON, gateway `success` callbacks, sitemap/robots, assets and broadcasting: actions of the page that owns them.
