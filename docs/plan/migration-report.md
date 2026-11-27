> **P12 run log (2026-11-27, written by hand; the tool regenerates everything below this box on each run).**
> 1. Batch 1 (#110): dry run + two real runs into QA `nogrgr_qa` (central catalogue, plans, settings, gateways, admins, 5 stores provisioned, promotions, affiliates) — idempotent.
> 2. Batch 2 (#113, central only): dry run against `dokan_v2` (0 errors), then real run into QA (`DB_DATABASE=nogrgr_qa TENANCY_DB_SUFFIX=_qa`) and repeated real runs (latest below: 0 rows created, everything updated) — idempotent. A bug found on QA (unknown template variables not detected) was fixed and the 8 templates it had written were removed before the latest run. New: 1 blog category, 2 posts, 3 FAQs, 15 pages, 0 of 21 e-mail templates (13 have no new event; the 8 mappable ones use variables the new events do not provide — support_email, portal_url, current_year, tracking_url… — so the new defaults are kept), 3 per-gram rates, 6 shipping rules/zones (3 without a country → draft), 3 subscription payments + paid invoices (7 belong to stores that no longer exist), 2 product requests + 3 messages, 1 manufacturing request, 35 refunds ($3,270.00). Request attachments were found in the old per-store storage (`storage/tenant<uuid>/app/public`) and copied to the private `central` disk.
> 3. `legacy:reconcile` on QA: **Differences: 0** (refunds $3,270.00 old = new, subscription payments $897.00 old = new; old settlements / payouts: none). Order figures are n/a: the old store DBs are not on this server.
> **Store-side data is not required for launch (client decision, #113):** the 5 old store DBs are absent here; the per-store steps stay fixture-tested and are reported as *info*. Consequently the 21 old return requests (all of e2e test orders) are skipped (their orders are not in the new DB) while the 35 refunds are kept with order numbers `L-<uuid prefix>`. Old order invoices (57) are not copied (rendered from the order).

# Legacy data migration report

- **Run:** 2026-11-27 02:04:12 → 2026-11-27 02:04:15
- **Mode:** write
- **Source:** dokan_v2 (+ store DBs)
- **Target:** nogrgr_qa
- **Steps:** all
- **Errors:** 0

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
| central | affiliate | 0 | 0 | 5 | 0 | 0 |
| central | blog_categories | 1 | 0 | 0 | 0 | 0 |
| central | blog_category | 0 | 0 | 1 | 0 | 0 |
| central | blog_posts | 2 | 0 | 0 | 0 | 0 |
| central | blog_post | 0 | 0 | 2 | 0 | 0 |
| central | faqs | 3 | 0 | 0 | 0 | 0 |
| central | faq | 0 | 0 | 3 | 0 | 0 |
| central | static_pages | 15 | 0 | 0 | 0 | 0 |
| central | page | 0 | 0 | 15 | 0 | 0 |
| central | email_templates | 21 | 0 | 0 | 0 | 21 |
| central | fixed_shipping_costs | 3 | 0 | 0 | 0 | 0 |
| central | shipping_fixed_cost | 0 | 0 | 3 | 0 | 0 |
| central | shipping_zones | 6 | 0 | 0 | 0 | 0 |
| central | shipping_rule | 0 | 0 | 6 | 0 | 0 |
| central | shipping_zone | 0 | 0 | 6 | 0 | 0 |
| central | shipping_zone_rates | 6 | 0 | 0 | 0 | 0 |
| central | payment_logs | 10 | 0 | 0 | 0 | 7 |
| central | invoice | 0 | 0 | 3 | 0 | 0 |
| central | payment_log | 0 | 0 | 3 | 0 | 0 |
| central | order_invoices | 57 | 0 | 0 | 0 | 57 |
| central | product_requests | 2 | 0 | 0 | 0 | 0 |
| central | product_request | 0 | 0 | 2 | 0 | 0 |
| central | product_request_messages | 2 | 0 | 0 | 0 | 0 |
| central | request_message:product | 0 | 0 | 3 | 0 | 0 |
| central | brand_requests | 1 | 0 | 0 | 0 | 1 |
| central | manufacturing_requests | 2 | 0 | 0 | 0 | 1 |
| central | manufacturing_request | 0 | 0 | 1 | 0 | 0 |
| central | request_message:manufacturing | 0 | 0 | 1 | 0 | 0 |
| central | return_requests | 21 | 0 | 0 | 0 | 21 |
| central | refunds | 35 | 0 | 0 | 0 | 0 |
| central | refund | 0 | 0 | 35 | 0 | 0 |

## Validation issues

| Level | Scope | Entity | Issue | Count | Old ids (first 15) |
|---|---|---|---|---:|---|
| info | central | email_templates | Old e-mail template has no new event (new default kept) | 13 | admin.invoice_issued, admin.order_alert, admin.shipping_escalation, admin.vendor_wallet_transaction, admin.tenant_subscription_activated, admin.tenant_subscription_renewal_reminder, admin.tenant_subscription_cancelled, tenant.order_processing, tenant.payment_receipt, tenant.wallet_transaction_receipt, tenant.subscription_renewal_reminder, admin.gateway_limit_warning, admin.gateway_limit_block |
| info | central | payment_gateways | Gateway not supported by the new system (skipped) | 44 | mollie, mollie, razorpay, razorpay, flutterwave, flutterwave, paystack, paystack, authorize_net, authorize_net, instamojo, instamojo, iyzico, iyzico, midtrans |
| info | central | refunds | Refund kept for an order that was not migrated (order number L-<uuid>) | 35 | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 |
| info | central | return_requests | Return of an order that was not migrated (skipped; store orders not required for launch) | 21 | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 |
| info | central | settings | Old setting has no new equivalent (not migrated) | 16 | app_name, marketplace_name, default_currency, default_language, mail_encryption, mail_queue, header_logo, footer_logo, favicon, facebook_url, twitter_url, instagram_url, youtube_url, linkedin_url, affiliate_default_commission_type |
| info | central | shipping_zones | Zone without a country migrated as a draft rule (assign countries in the admin) | 3 | 1, 2, 3 |
| info | central | shipping_zones | Old branch link of the zone not migrated | 3 | 4, 5, 6 |
| info | store:0fc05b74-b605-4fa7-8d79-dfab8c50bab1 | store | Old store database not on the server: store data not migrated (not required for launch, decision #113) | 1 | 0fc05b74-b605-4fa7-8d79-dfab8c50bab1 |
| info | store:6e70e6e5-49d5-40cf-b66c-675dedf2c8dd | store | Old store database not on the server: store data not migrated (not required for launch, decision #113) | 1 | 6e70e6e5-49d5-40cf-b66c-675dedf2c8dd |
| info | store:d2a5bdc4-a66d-47e1-bf84-7fdc5c62aecf | store | Old store database not on the server: store data not migrated (not required for launch, decision #113) | 1 | d2a5bdc4-a66d-47e1-bf84-7fdc5c62aecf |
| info | store:e2e-central | store | Old store database not on the server: store data not migrated (not required for launch, decision #113) | 1 | e2e-central |
| info | store:e2e-sync | store | Old store database not on the server: store data not migrated (not required for launch, decision #113) | 1 | e2e-sync |
| warning | central | admins | Old custom admin role not mapped (assign a role after cut-over) | 1 | 4 |
| warning | central | brand_requests | Request of a store that was not migrated (skipped) | 1 | 1 |
| warning | central | email_templates | Old e-mail template uses variables without a new equivalent (new default kept) | 8 | tenant.order_confirmation, tenant.shipping_update, tenant.order_delivered, tenant.order_cancelled, tenant.refund_processed, tenant.subscription_activated, central.registration_complete_link, central.tenant_welcome |
| warning | central | manufacturing_requests | Request of a store that was not migrated (skipped) | 1 | 1 |
| warning | central | payment_logs | Payment of a store that was not migrated (skipped) | 7 | 1, 2, 3, 4, 5, 6, 7 |
| warning | central | products | Image file missing in old storage | 14 | 153, 153, 153, 153, 153, 153, 153, 154, 154, 154, 154, 154, 154, 154 |
| warning | central | support_tickets | Ticket of a store that was not migrated (skipped) | 4 | 1, 2, 3, 4 |

## Notes

- Shipping companies: the old system had none; create them in Admin › Shipping.
- Old order invoices are not copied: the new system renders the invoice from the order (checked against migrated orders where present).
- finance:backfill-ledger exit code 0.
- permissions:sync exit code 0.

## Finance reconciliation

Old vs new, USD. Diff must be 0 (n/a = old figure not available on this server).

| Scope | Metric | Old | New | Diff |
|---|---|---:|---:|---:|
| vendor (#3) | orders (count) | n/a | 0 | n/a |
| vendor (#3) | orders grand total | n/a | $0.00 | n/a |
| vendor (#3) | platform profit | n/a | $0.00 | n/a |
| vendor (#3) | vendor profit | n/a | $0.00 | n/a |
| vendor (#3) | refunds | $0.00 | $0.00 | $0.00 |
| vendor (#3) | subscription payments | $299.00 | $299.00 | $0.00 |
| vendor (#3) | settlements | $0.00 | $0.00 | $0.00 |
| vendor (#3) | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| vendor (#3) | payouts | $0.00 | $0.00 | $0.00 |
| vendor (#3) | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| vvv (#4) | orders (count) | n/a | 0 | n/a |
| vvv (#4) | orders grand total | n/a | $0.00 | n/a |
| vvv (#4) | platform profit | n/a | $0.00 | n/a |
| vvv (#4) | vendor profit | n/a | $0.00 | n/a |
| vvv (#4) | refunds | $0.00 | $0.00 | $0.00 |
| vvv (#4) | subscription payments | $299.00 | $299.00 | $0.00 |
| vvv (#4) | settlements | $0.00 | $0.00 | $0.00 |
| vvv (#4) | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| vvv (#4) | payouts | $0.00 | $0.00 | $0.00 |
| vvv (#4) | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| rrr (#5) | orders (count) | n/a | 0 | n/a |
| rrr (#5) | orders grand total | n/a | $0.00 | n/a |
| rrr (#5) | platform profit | n/a | $0.00 | n/a |
| rrr (#5) | vendor profit | n/a | $0.00 | n/a |
| rrr (#5) | refunds | $0.00 | $0.00 | $0.00 |
| rrr (#5) | subscription payments | $299.00 | $299.00 | $0.00 |
| rrr (#5) | settlements | $0.00 | $0.00 | $0.00 |
| rrr (#5) | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| rrr (#5) | payouts | $0.00 | $0.00 | $0.00 |
| rrr (#5) | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| E2E sync store (#6) | orders (count) | n/a | 0 | n/a |
| E2E sync store (#6) | orders grand total | n/a | $0.00 | n/a |
| E2E sync store (#6) | platform profit | n/a | $0.00 | n/a |
| E2E sync store (#6) | vendor profit | n/a | $0.00 | n/a |
| E2E sync store (#6) | refunds | $1,536.00 | $1,536.00 | $0.00 |
| E2E sync store (#6) | subscription payments | $0.00 | $0.00 | $0.00 |
| E2E sync store (#6) | settlements | $0.00 | $0.00 | $0.00 |
| E2E sync store (#6) | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| E2E sync store (#6) | payouts | $0.00 | $0.00 | $0.00 |
| E2E sync store (#6) | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| E2E central store (#7) | orders (count) | n/a | 0 | n/a |
| E2E central store (#7) | orders grand total | n/a | $0.00 | n/a |
| E2E central store (#7) | platform profit | n/a | $0.00 | n/a |
| E2E central store (#7) | vendor profit | n/a | $0.00 | n/a |
| E2E central store (#7) | refunds | $1,734.00 | $1,734.00 | $0.00 |
| E2E central store (#7) | subscription payments | $0.00 | $0.00 | $0.00 |
| E2E central store (#7) | settlements | $0.00 | $0.00 | $0.00 |
| E2E central store (#7) | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| E2E central store (#7) | payouts | $0.00 | $0.00 | $0.00 |
| E2E central store (#7) | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| Platform | refunds | $3,270.00 | $3,270.00 | $0.00 |
| Platform | subscription payments | $897.00 | $897.00 | $0.00 |
| Platform | settlements | $0.00 | $0.00 | $0.00 |
| Platform | settlements (ledger) | $0.00 | $0.00 | $0.00 |
| Platform | payouts | $0.00 | $0.00 | $0.00 |
| Platform | payouts (ledger) | $0.00 | $0.00 | $0.00 |
| Platform | orders grand total | n/a | $0.00 | n/a |
| Platform | platform profit | n/a | $0.00 | n/a |
| Platform | vendor profit | n/a | $0.00 | n/a |

**Differences: 0**

Old rows of stores that are not in the new system (not compared):

- payment_logs: 7 row(s), $2,093.00

- vendor (#3): old store DB 6e70e6e5-49d5-40cf-b66c-675dedf2c8dd not on the server — order figures n/a (store data not required for launch).
- vvv (#4): old store DB d2a5bdc4-a66d-47e1-bf84-7fdc5c62aecf not on the server — order figures n/a (store data not required for launch).
- rrr (#5): old store DB 0fc05b74-b605-4fa7-8d79-dfab8c50bab1 not on the server — order figures n/a (store data not required for launch).
- E2E sync store (#6): old store DB e2e-sync not on the server — order figures n/a (store data not required for launch).
- E2E central store (#7): old store DB e2e-central not on the server — order figures n/a (store data not required for launch).
