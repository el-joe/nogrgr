# Old → new data migration (P12)

Tool: `php artisan legacy:migrate` (Platform module, `app/Modules/Platform/Legacy`, decision #110).
Source: the old multi-vendor project (default `--source=/var/www/multi-vendor`): its `.env` gives the DB server, the central DB (`dokan_v2`) and the old `APP_KEY`; each old store DB name comes from `tenants.data.tenancy_db_name` (`dokan_v2_<uuid>`). The old databases are **only read**.

## Usage

```
php artisan legacy:migrate --dry-run                       # read + validate everything, write only the report
php artisan legacy:migrate --force                         # full run (asks for confirmation without --force)
php artisan legacy:migrate --step=catalog --step=tenants   # some steps only
php artisan legacy:migrate --tenant=<old uuid> --step=orders
php artisan legacy:migrate --fresh-mapping                 # forget the id map (rows re-matched by natural keys)
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
| store_catalog *(per store)* | products (+variants), tenant_product_overrides, tenant_product_variant_overrides, tenant_field_overrides | store_products, store_product_variants, product_overrides | **#65:** central selections keep only the central id + store business data (active, featured, sort, markup per country, variant markup per central variant); approved content overrides → product_overrides; own products copied in full |
| customers *(per store)* | customers, customer_addresses | customers, customer_addresses | hashes and social ids kept; address without country / street reported |
| orders *(per store)* | orders, order_items, order_activities | orders, order_items, order_payments, order_status_history, central order_facts | number = old order id, uuid = old uuid; line money copied 1:1 in cents; order sums = stored line sums (ORDER_FORMULAS §1), platform/vendor split copied and checked (= grand total); `order_facts` written through `RecordOrderFacts` |
| engagement *(per store)* | product_rates, favorites | product_reviews (approved, tied to the customer's purchase), customer_favorites | ratings without a matching purchase reported |
| store_promotions *(per store)* | coupons, flash_sales (+products) | store_coupons, store_flash_sales (+products) | store copies of central promotions skipped (#65) |
| finalize | — | — | `finance:backfill-ledger --tenant=<migrated>` then `permissions:sync` (not in dry run) |

## Validation (report)

Errors: missing old store DB, failed provisioning, platform + vendor ≠ grand total (> 1 cent), negative totals / prices, undecryptable secrets, selected central product not migrated, taken custom domain, step failures. Warnings / info: orphans (city without country, option without attribute, category parent, order line product gone), missing image files, sub-cent rounding, unmapped settings / credential fields / gateways / admin roles, non-reusable owner hashes (cost above ours → owner resets password).

## Not migrated (by design or still open)

- Old store-side content that has no new home yet or is regenerated: store settings / themes / page sections / colours (new theme system, vendors re-pick), banners, email templates, static texts (re-synced from the new catalogue at provisioning), translation overrides, mobile app settings, maintenance windows, subscribers.
- Central: blog / FAQ / static pages, email templates, shipping zones & rates (new Shipping model differs), invoices / payment logs / refunds / return requests (central `return_requests` 21, `refunds` 34, `invoices` 46 rows), vendor settlements & payouts, product / brand / manufacturing requests, affiliate conversions / payouts (none in the old DB).
- Own-product images of stores (old per-store storage), support message attachments.

## Cut-over runbook

1. Freeze writes on the old system (maintenance mode).
2. `php artisan legacy:migrate --dry-run` on production → review `docs/plan/migration-report.md` (0 errors except accepted ones).
3. `php artisan legacy:migrate --force` → review the report; run the queue worker for image conversion.
4. Finance reconciliation: compare per store Σ old (platform_profit, vendor_profit, grand total) with `order_facts` / ledger (target 0 diff, P12 gate).
5. DNS: point custom domains; old `<slug>.dokan-v2` hosts redirect to the new sub-domain.
6. Re-run step(s) as needed: the tool is idempotent.
