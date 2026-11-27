> **P12 run log (2026-11-27, written by hand; the tool regenerates everything below this box on each run).**
> 1. Dry run against the old DBs (`dokan_v2` + store DBs): 0 rows written; 173 products, 1 275 variants, 2 325 images, 13 categories, 1 206 attribute options, 5 stores, 9 coupons, 8 flash sales would migrate.
> 2. Real run into QA `nogrgr_qa` (stores suffix `_qa`): all central steps written, 5 stores provisioned (ids 3–7, all active), ledger backfill + permissions sync exit 0. One fix found and applied (vendor affiliate already created at provisioning → matched by store).
> 3. Second real run (below): every row **updated / matched, 0 created** — idempotent. 2 341 image-conversion jobs are queued on QA's `database` queue.
> **Blocking finding:** the 5 old store databases (`dokan_v2_<uuid>`) do not exist on this server, so store-side data (store catalogue selections, customers, orders, reviews, store coupons) could not be migrated here; those steps are covered by the fixture tests. The 4 old support tickets belong to a store (`e57e205f-…`) that no longer exists in the old central DB.
> Media were copied into `backend/storage/app/public/catalog/products/legacy/` (the public disk shared by dev and QA on this machine).

# Legacy data migration report

- **Run:** 2026-11-27 00:33:17 → 2026-11-27 00:33:20
- **Mode:** write
- **Source:** dokan_v2 (+ store DBs)
- **Target:** nogrgr_qa
- **Steps:** all
- **Errors:** 5

## Counts

| Scope | Entity | Read | Created | Updated | Matched | Skipped |
|---|---|---:|---:|---:|---:|---:|
| central | languages | 4 | 0 | 0 | 0 | 0 |
| central | language | 0 | 0 | 4 | 0 | 0 |
| central | currencies | 155 | 0 | 0 | 0 | 0 |
| central | currency | 0 | 0 | 0 | 155 | 0 |
| central | countries | 198 | 0 | 0 | 0 | 0 |
| central | country | 0 | 0 | 0 | 198 | 0 |
| central | cities | 1028 | 0 | 0 | 0 | 0 |
| central | city | 0 | 0 | 0 | 1028 | 0 |
| central | plans | 2 | 0 | 0 | 0 | 0 |
| central | plan | 0 | 0 | 2 | 0 | 0 |
| central | settings | 32 | 0 | 16 | 0 | 16 |
| central | payment_gateways | 56 | 0 | 0 | 0 | 44 |
| central | payment_gateway | 0 | 0 | 12 | 0 | 0 |
| central | gateway_credentials | 0 | 0 | 2 | 0 | 0 |
| central | payment_gateway_config | 0 | 0 | 12 | 0 | 0 |
| central | categories | 13 | 0 | 0 | 0 | 0 |
| central | category | 0 | 0 | 13 | 0 | 0 |
| central | attributes | 3 | 0 | 0 | 0 | 0 |
| central | attribute | 0 | 0 | 3 | 0 | 0 |
| central | attribute_options | 1206 | 0 | 0 | 0 | 0 |
| central | attribute_option | 0 | 0 | 1206 | 0 | 0 |
| central | badges | 5 | 0 | 0 | 0 | 0 |
| central | badge | 0 | 0 | 5 | 0 | 0 |
| central | labels | 12 | 0 | 0 | 0 | 0 |
| central | label | 0 | 0 | 12 | 0 | 0 |
| central | products | 173 | 0 | 0 | 0 | 0 |
| central | product | 0 | 0 | 173 | 0 | 0 |
| central | catalog_media | 0 | 0 | 2325 | 0 | 0 |
| central | variants | 1275 | 0 | 0 | 0 | 0 |
| central | variant | 0 | 0 | 1275 | 0 | 0 |
| central | admins | 2 | 0 | 0 | 0 | 0 |
| central | admin | 0 | 0 | 2 | 0 | 0 |
| central | tenants | 5 | 0 | 0 | 0 | 0 |
| central | owners | 5 | 0 | 0 | 0 | 0 |
| central | owner | 0 | 0 | 5 | 0 | 0 |
| central | tenant | 0 | 0 | 5 | 0 | 0 |
| central | domains | 5 | 0 | 0 | 0 | 5 |
| central | coupons | 9 | 0 | 0 | 0 | 0 |
| central | coupon | 0 | 0 | 9 | 0 | 0 |
| central | flash_sales | 8 | 0 | 0 | 0 | 0 |
| central | flash_sale | 0 | 0 | 8 | 0 | 0 |
| central | support_tickets | 4 | 0 | 0 | 0 | 4 |
| central | affiliates | 5 | 0 | 0 | 0 | 0 |
| central | affiliate | 0 | 0 | 1 | 4 | 0 |

## Validation issues

| Level | Scope | Entity | Issue | Count | Old ids (first 15) |
|---|---|---|---|---:|---|
| error | store:0fc05b74-b605-4fa7-8d79-dfab8c50bab1 | store | Old store database not found on the server (store data not migrated) | 1 | 0fc05b74-b605-4fa7-8d79-dfab8c50bab1 |
| error | store:6e70e6e5-49d5-40cf-b66c-675dedf2c8dd | store | Old store database not found on the server (store data not migrated) | 1 | 6e70e6e5-49d5-40cf-b66c-675dedf2c8dd |
| error | store:d2a5bdc4-a66d-47e1-bf84-7fdc5c62aecf | store | Old store database not found on the server (store data not migrated) | 1 | d2a5bdc4-a66d-47e1-bf84-7fdc5c62aecf |
| error | store:e2e-central | store | Old store database not found on the server (store data not migrated) | 1 | e2e-central |
| error | store:e2e-sync | store | Old store database not found on the server (store data not migrated) | 1 | e2e-sync |
| info | central | payment_gateways | Gateway not supported by the new system (skipped) | 44 | mollie, mollie, razorpay, razorpay, flutterwave, flutterwave, paystack, paystack, authorize_net, authorize_net, instamojo, instamojo, iyzico, iyzico, midtrans |
| info | central | settings | Old setting has no new equivalent (not migrated) | 16 | app_name, marketplace_name, default_currency, default_language, mail_encryption, mail_queue, header_logo, footer_logo, favicon, facebook_url, twitter_url, instagram_url, youtube_url, linkedin_url, affiliate_default_commission_type |
| warning | central | admins | Old custom admin role not mapped (assign a role after cut-over) | 1 | 4 |
| warning | central | products | Image file missing in old storage | 14 | 153, 153, 153, 153, 153, 153, 153, 154, 154, 154, 154, 154, 154, 154 |
| warning | central | support_tickets | Ticket of a store that was not migrated (skipped) | 4 | 1, 2, 3, 4 |

## Notes

- finance:backfill-ledger exit code 0.
- permissions:sync exit code 0.
