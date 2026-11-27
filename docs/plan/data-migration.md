# Old → new data migration (P12)

Tool: `php artisan legacy:migrate` (Platform module, `app/Modules/Platform/Legacy`, decisions #110, #113) + `php artisan legacy:reconcile` (finance check).

> **Scope (client decision 2026-11-27, #113): fresh launch for stores.** Old store-side data (catalogue selections, customers, orders, reviews, store coupons, store settings / themes / banners / e-mail templates / translations, own-product images) is **not required for launch**. The per-store steps below stay in the tool (fixture-tested) and run when an old store DB is present; a missing store DB is reported as *info*, not an error. Central products come from the Neozena import (`catalog:import-neozena`, #112), not from this tool.
Source: the old multi-vendor project (default `--source=/var/www/multi-vendor`): its `.env` gives the DB server, the central DB (`dokan_v2`) and the old `APP_KEY`; each old store DB name comes from `tenants.data.tenancy_db_name` (`dokan_v2_<uuid>`). The old databases are **only read**.

## Usage

```
php artisan legacy:migrate --dry-run                       # read + validate everything, write only the report
php artisan legacy:migrate --force                         # full run (asks for confirmation without --force)
php artisan legacy:migrate --step=catalog --step=tenants   # some steps only
php artisan legacy:migrate --tenant=<old uuid> --step=orders
php artisan legacy:migrate --fresh-mapping                 # forget the id map (rows re-matched by natural keys)
php artisan legacy:reconcile                               # finance old vs new → "Finance reconciliation" section of the report, exit 1 on any difference
  --no-media   skip copying image files      --report=<path>  (default docs/plan/migration-report.md)
```

Target = the app's configured DB (production / QA). QA run: `DB_DATABASE=nogrgr_qa TENANCY_DB_SUFFIX=_qa QUEUE_CONNECTION=database MAIL_MAILER=log php artisan legacy:migrate --force`. Never against the dev DB `nogrgr`.

## Guarantees

- **Idempotent / repeatable.** Every written row is keyed by its old id in `legacy_central_ids` (central) or `legacy_store_ids` (each store DB): `(entity, legacy_key) → new_id`, unique. A re-run updates mapped rows; reference data already in the new DB (languages, currencies, countries, cities, badge types, gateways) is matched by natural key instead of duplicated. Pivots are replaced per owner row.
- **Dry run** writes nothing (no rows, no files, no map, no provisioning): new rows get in-memory placeholder ids so later steps still resolve references; the report lists counts and findings.
- **Chunked reads, one transaction per chunk** (`chunkById` 200–500 rows); translations, files, pivots, variants and order lines are prefetched per chunk (no N+1). Steps are isolated: a failing step is reported and the run continues.
- **Secrets**: gateway credentials and secret settings are decrypted with the OLD `APP_KEY` and re-encrypted with ours (`encrypted:array` / settings repository); values are never printed, logged or put in the report.
- **Money**: USD minor units via `App\Shared\Money\Money::of()` (old `OrderPricing::money()` rounding). Orders are **not re-priced**: the old stored line snapshot is copied; sub-cent values are reported.

## Step order and mapping

| Step | Old (central `dokan_v2`) | New | Notes |
|---|---|---|---|
| localization | languages, currencies, countries, cities | same | matched by code / iso2 / (country, slug); seed names kept, only store-facing flags (language price, active) move; unknown rows inserted; orphan cities reported |
| plans | packages | plans | price → monthly or yearly by old term; limits → `PlanLimit` keys, negative = unlimited |
| settings | app_settings | integration_settings | mail, AI (OpenAI), affiliates, general support e-mail / archive days; other keys listed as "no new equivalent" |
| gateways | payment_gateways (code × type) | payment_gateways + payment_gateway_configs | type vendor_payments → purpose vendor_payments, orders → tenant_registration; credential keys renamed (stripe key/secret, myfatoorah token); unsupported gateways skipped |
| catalog | categories (+translations, thumbnail), variations/options, product_badges, product_labels, products, product_variants, pivots, files | categories, attributes/attribute_options, product_badges (seeded types), product_labels, products, product_variants, category_product, product_attribute, product_label_product, product_badge_product, product_variant_option, catalog_media | translations → `{locale: text}` JSON; fixed-shipping maps re-keyed to new country ids and converted to cents; images copied to `public:catalog/products/legacy/...` and queued for webp/avif conversion; `-thumb` files skipped; `external_id` kept with source neozena |
| admins | admins, admin_roles | admins (+ super-admin role) | bcrypt hashes kept; custom roles reported |
| tenants | tenants (uuid, `data` JSON), tenant_owners, domains, product_tenant_assignments | owners, tenants, domains, product_tenant_assignments | store created Pending with the provisioning payload and provisioned synchronously by `TenantProvisioningService` (DB, migrations, ar/en, static texts, owner staff, plan); no welcome mail (`legacy_id` in payload); suspended stores re-suspended; custom domains kept, old sub-domains replaced by the new one |
| promotions | central_coupons, central_flash_sales (+ country/product pivots) | coupons, flash_sales (store_scope all) | #65 merge model |
| support | support_tickets, support_ticket_messages | support_tickets, support_ticket_messages | reference `T-<1000+id>`; attachments reported |
| affiliates | affiliates | affiliates | % → bps, fixed / balances → cents; IBAN re-encrypted; a store's partner account created at provisioning is matched by tenant |
| content | blog_categories, blog_posts, faqs, static_pages (+ `translations`) | blog_categories, blog_posts, faqs, pages | `{locale: text}` JSON; FAQ group from the old `category` text; pages matched by slug (seeded system pages take the old content, `is_system` kept), target central |
| email_templates | email_templates (+ email_template_translations) | email_templates (matched by event) | old action → new event (order confirmation / shipped / delivered / cancelled / refund processed, vendor subscription activated, registration link, welcome); `{{order_number}}` → `{{ order.number }}` etc.; bodies through `EmailHtmlSanitizer`; unknown variable or no event → new default kept (reported) |
| shipping | fixed_shipping_costs, shipping_zones, shipping_zone_rates | shipping_fixed_costs (by country), shipping_rules + shipping_rule_countries + shipping_zones + shipping_zone_bands | one rule per old zone (its country), one all-cities zone, bands in grams / cents; zone without country → draft rule; inactive rates and branch links reported; no old shipping companies |
| billing | payment_logs, invoices | payment_logs + invoices/invoice_items | captured payment → paid subscription invoice `INV-<year>-L<id>` (no tax); raw gateway payload (card data) not copied; old `invoices` are order invoices → rendered from the order in the new system, checked against `order_facts` |
| settlements | vendor_settlements, tenant_payouts | order_settlements, tenant_payouts + ledger | cents 1:1; references `ST-L<id>` / `PO-L<id>`; payee = bank name + last 4 only; paid rows posted via `FinancePostings` (idempotent keys) |
| requests | product_requests, brand_requests, manufacturing_requests (+ messages, payment requests) | catalog_product/brand/manufacturing_requests, catalog_request_messages, catalog_request_attachments, catalog_request_payments | statuses mapped to the new enums; request-level files on an opening vendor message (as the new submit flow); files copied to the private `central` disk |
| store_catalog *(per store)* | products (+variants), tenant_product_overrides, tenant_product_variant_overrides, tenant_field_overrides | store_products, store_product_variants, product_overrides | **#65:** central selections keep only the central id + store business data (active, featured, sort, markup per country, variant markup per central variant); approved content overrides → product_overrides; own products copied in full |
| customers *(per store)* | customers, customer_addresses | customers, customer_addresses | hashes and social ids kept; address without country / street reported |
| orders *(per store)* | orders, order_items, order_activities | orders, order_items, order_payments, order_status_history, central order_facts | number = old order id, uuid = old uuid; line money copied 1:1 in cents; order sums = stored line sums (ORDER_FORMULAS §1), platform/vendor split copied and checked (= grand total); `order_facts` written through `RecordOrderFacts` |
| engagement *(per store)* | product_rates, favorites | product_reviews (approved, tied to the customer's purchase), customer_favorites | ratings without a matching purchase reported |
| store_promotions *(per store)* | coupons, flash_sales (+products) | store_coupons, store_flash_sales (+products) | store copies of central promotions skipped (#65) |
| after_sales *(central, after the store steps)* | return_requests (+ notes, media), refunds | return_requests, return_request_events, return_request_media, refunds | a return needs its order line → migrated only when the order is in the new DB (else skipped + reported); refunds always kept in cents (order number from `order_facts`, else `L-<uuid prefix>`) |
| finalize | — | — | `finance:backfill-ledger --tenant=<migrated>`, `permissions:sync`, then the finance reconciliation section (not in dry run) |

## Validation (report)

Errors: failed provisioning, finance reconciliation difference, platform + vendor ≠ grand total (> 1 cent), negative totals / prices, undecryptable secrets, selected central product not migrated, taken custom domain, step failures. Warnings / info: missing old store DB (not required for launch), returns of orders not in the new DB, refunds / payments / requests of stores that no longer exist, e-mail templates kept at the new default, zones without a country, orphans (city without country, option without attribute, category parent, order line product gone), missing image files, sub-cent rounding, unmapped settings / credential fields / gateways / admin roles, non-reusable owner hashes (cost above ours → owner resets password).

## Finance reconciliation (`legacy:reconcile`)

Read only on both sides; also appended to the report by every real `legacy:migrate`. Per migrated store and for the platform, in USD cents (each old value rounded per row like the migration):

| Metric | Old | New |
|---|---|---|
| orders grand total / platform profit / vendor profit | old store DB `orders` (platform + vendor = grand) | `order_facts` (n/a when the old store DB is absent) |
| refunds | central `refunds.amount` | `refunds.amount` |
| subscription payments | `payment_logs` paid | `payment_logs` captured |
| settlements / settlements (ledger) | `vendor_settlements.total` (all / paid) | `order_settlements.amount` / ledger `settlement` postings |
| payouts / payouts (ledger) | `tenant_payouts` paid | `tenant_payouts` paid / ledger `payout` postings |

Old rows of stores that are not in the new system are listed apart (not compared). The gate is **Differences: 0**; any difference makes the command exit 1 and adds an error to the report.

## Not migrated (by design)

- **Store-side data — not required for launch (#113):** store catalogue selections, customers, orders, reviews, favourites, store coupons / flash sales (steps exist, fixture-tested), and store settings / themes / page sections / colours, banners, store e-mail templates, translation overrides / static texts, own-product images, mobile app settings, maintenance windows, subscribers (no steps: vendors set these up in the new panel).
- Central: product edit requests, tenant change requests, domain requests (transient workflow rows), affiliate conversions / payouts (none in the old DB), default banners (none in the old DB), old order invoices (rendered from the order).
- Support ticket attachments of the old central tickets (reported; the old tickets belong to a store that no longer exists).

## Cut-over runbook

1. Freeze writes on the old system (maintenance mode).
2. `php artisan legacy:migrate --dry-run` on production → review `docs/plan/migration-report.md` (0 errors except accepted ones).
3. `php artisan legacy:migrate --force` → review the report; run the queue worker for image conversion.
4. Finance reconciliation: `php artisan legacy:reconcile` → "Finance reconciliation" section must show **Differences: 0** (P12 gate).
5. DNS: point custom domains; old `<slug>.dokan-v2` hosts redirect to the new sub-domain.
6. Re-run step(s) as needed: the tool is idempotent.
