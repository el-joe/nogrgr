# Multi-Agent Progress

Last updated: 2026-11-25 · Branch: **main** (all work is built and merged on `main`; the old `rebuild` branch is merged in and no longer used).

Rules for agents: at most **2 agents at a time**, small briefs, resume only pending/failed tasks, never discard committed work. Update this file after every batch.

## Completed

| Phase | Work | Commit(s) |
|---|---|---|
| P0 Foundations | Shared kernel, tenancy, money, panels, auth, navigation, QA gate **PASS** (610/611 tests; 1 flaky in `tests/Feature/Identity/CustomerAuthTest.php`) | history up to `beff376` |
| P2 (part) | Identity permission catalog + admin/vendor navigation (`Identity/ModuleServiceProvider.php`, `Identity/Support/PermissionCatalog.php`, `config/navigation/{admin,vendor}.php`, `lang/{ar,en}/navigation.php`) | `7e94296` |
| P2 (part) | Billing: `AddonPurchase` model/factory/migration, `AddonPurchaseStatus`/`AddonType` enums, repository + contract | `1c5addd` |
| P2 (part) | Localization: languages/currencies/tenant_languages migrations, models, factories, enums (`TenantLanguageSource`, `TextDirection`), repositories + contracts, `currencies.json`/`languages.json` data | `1c5addd` |
| P2 (part) | `Localization/Services/LanguageService.php` | `bc7fdb1` |
| P3 (part) | Catalog schema (3 migrations: categories, products, labels/badges/media), 16 enums, 10 models | `1d522fd` |
| P3/P5 (part) | `Commerce/Pricing` (`CountryPriceTable`, `FixedShipping`, `ProfitRule`, `UnitPrice`, `UnitPricing`), `ProfitType` enum, `tests/Unit/Commerce/UnitPricingTest.php` | `1d522fd` |
| Merge | All worktree branches merged into `main` | `623b074` |
| **P5 batch 4 (P5 complete)** | Ledger/settlements/payouts/transactions, vendor money flow, vendor coupons & flash sales, customer returns API, refund webhooks (#72) | `55ef85a` |
| **QA gate P3–P6 + vendor** | **PASS** — 182 URLs × 2 modes, flows end to end, #65 verified live, money hand-checked; fixes QA-P5-001…006; suite 1861 green | `5dee252` |
| Settings from DB (#69) | env → DB settings + admin pages (Geocoding, Platform Billing/Checkout/Domains), EnvUsageTest, central-key webhook routing, store robots, og image (#73) | `3d00bd4` |
| P5 batch 3 | Admin orders/shipping queue/returns/refunds/return policy, vendor orders/returns/refunds/settle/gateways, AfterSales module, branch stock deduction (#71) | `32334b0` |
| **P6 Central website** | Home, templates, pricing, about, contact, FAQs, legal, blog, newsletter, SEO, errors; Content admin; store login reset (#70); suite 1787 green | `5ae4bfa` |
| P5 batch 2 + vendor requests | Cart/checkout/orders/payments + /store-api/v1 (decision #68); vendor requests hub, inbox, setup wizard (#67); suite 1674 green | `d7d9f18` |
| P5 batch 1 | OrderPricing/OrderComposer (golden + 700-cart invariant), central+store coupons & flash sales merged by source, admin ADM-025/026; decision #66 | `44996c1` |
| P3 Vendor catalog | VEN-010…016: store products from central + per-field tenant overrides via approved requests (#65), markup price list, import new, edit requests, sort, opportunities, own products; suite 1606 green | `03e6361` |
| **P4 Shipping** | Fixed costs/gram, rules/zones/bands, companies + day rules + stop periods, estimator, settings, popup days; pure quote/estimator with golden tests; branches on real zones (ADM-031…035) | `4dd7689` |
| P2 Vendor panel foundation | VEN-001…008: vendor auth, one-time impersonation + timed banner, shell + bell, onboarding tour/checklist, setup widget, dashboard (VEN-007 wizard + VEN-009 KPIs partial) | `4031059` |
| P3 Requests & import | Requests center ADM-018…018i (shared thread, payments → invoices, overrides, tenant changes), Neozena import ADM-013, products bulk actions | `dab5afa` |
| P2 QA fixes | QA-P2-004…008: PHPStan 0 outside P3 WIP, resumable provisioning, modal pages, categories at sign-up, gateway tiles, select2 chevron, OWN-009 | `25ef1cf` |
| **P2 QA gate** | **PASS** — 100+ rows verified in browser (en/ar, dark/light, 375px), mobile overflow fixes; open QA-P2-004…008 | `9e9415e` |
| P2 Static texts & translations | Per-tenant `tenant_static_texts` + `__st()`, sync command, ADM-092 editor (DB overrides), ADM-093 key locks, ADM-094 purchases; suite 1372 green | `7c239c2` |
| P2 Owner portal | /my-account: stores + SSO, select store, subscriptions renew, domains + DNS, invoices, profile + social; suite 1318 green | `ec3eb5a` |
| P3 Products & branches | Branches + stock (Inventory), products list/price list/tabbed form/variants/media/sort, AiTranslatable contract; suite 1239 green | `c5646a9` |
| P2 Registration | Central sign-up wizard (trial/paid), Stripe/PayPal checkout + idempotent webhooks, Owner identity + guard, Google/Apple sign-in, add another store (WEB-016…019b, OWN-007); suite 1210 green | `f29d9cf` |
| P3 Catalog taxonomy | Categories tree/sort/product order, attributes, labels, badges hub (ADM-014…017g); suite 1154 green | `4adb0eb` |
| P2 Integrations | Gateways, mail, Google/Apple, Reverb, Cloudflare, AI translation provider + TranslationClient, general settings, email templates, billing notifications; suite 1117 green | `36ae948` |
| P2 Vendors | Provisioning service/job, vendors list/create/profile tabs, pending registrations, compliance, domains + DNS templates, central WEB-018/018a/020; suite 1071 green | `95488be` |
| P2 Identity | Admin profile, Notifications module (central tables, preferences, live bell), admins, roles matrix, audit log; suite 911/912 (UiKitTest fixed after) | `d53b821` |
| P2 Billing | Plans, subscriptions (lifecycle + hourly expiry), invoices (print), payment logs (capture/refund), add-on + language purchase (`LanguagePurchasePaid`); 65 tests; suite 805 green | `ee0fb8e` |
| P2 Localization | Languages (184 ISO, one price/lang #45), Currencies (+ daily rate sync), Countries admin; tenant language services; 88 tests | `d27aab1` |
| Verify | Recovered WIP verified: migrate:fresh clean, suite **660/660**; repository bindings added; QA-P0-016 and CustomerAuthTest confirmed OK | `e794206` |

## Pending (by phase)

- **P2 Platform & Identity (rest):** services, controllers, FormRequests, DTOs, panel UI + DataTables for admins/roles, languages, countries, currencies, plans, tenant provisioning, subscriptions, integrations, notifications settings; tests.
- **P3 Catalog & Inventory (rest):** repositories, services, controllers, panel UI, imports, media conversions, inventory; tests.
- **P4 Shipping · P5 rest: ledger/settlements/payouts ADM-050…053, vendor coupons/flash VEN-040/041 · P5 QA gate · P6 Central website · P7 Themes · P8 Vendor panel · P9 Affiliates/Support/Content/Reports · P10 AI · P11 Mobile · P12 Migration & launch:** not started.
- **P1 Design:** Figma additions as needed per module (never edit existing client frames).

## Dependencies

Critical path: **P0 → P2 → P3 → P5 → P7 / P11**.
- P3 needs P2 (currencies, languages, tenants, permissions).
- P4 needs P2 countries; P5 needs P3 + P4 + `Commerce\Pricing`.
- P6/P8/P9 need P2 + P3; P7 needs P5; P10 needs P3; P11 needs the Mobile API (after P5); P12 last.
- QA-P0-008/009 deferred parts (Google sign-in, country filter, Export report) wait for P2/P9.

## In progress (batch P2-1, started 2026-11-25, agent team of 2)

- **integrations** (started after identity finished): Integrations tabs (gateways, mail, Google/Apple, Reverb, Cloudflare, AI translation provider #43 + TranslationClient), general settings, email templates, Billing events → notifications.
- **vendorrequests**: vendor requests hub VEN-050…050j (+ vendor channel auth, pay requests), notifications inbox VEN-090, setup wizard VEN-007, real ImportProducts step.
- **themes** (P7 batch 1): storefront foundation (theme resolution, section library, banners #65, reviews, search, SEO, sitemap) + Ecommet all SF-ECO rows; ComfyUI demo store.
- **polish**: QA-P5-009 (VEN-017/018 store categories & badges), 012–016, VEN-041b button, admin promo modals, ledger back-fill, overdue threshold → settings, picker case, VEN-060 subscription page.
- Shared test DB: every test command runs under `flock /tmp/nogrgr-tests.lock`. Agents don't commit; orchestrator commits after the batch.
- Next: P5 QA re-check (finance pages, browser) · P7 themes · P8 vendor panel rest · P9 · P10 AI · P11 mobile · P12 · Language purchases tab (ADM-094) · per-tenant `tenant_static_texts` (#45) · P2 QA gate.


## 2026-11-26 update
- polish batch committed cb8e8e7: store categories & badges (VEN-017/018, #74), vendor subscription VEN-060 (#75), finance:backfill-ledger, QA-P5-009/012-016 fixed, VEN-041b, admin promotion modals, overdue setting, case-insensitive picker. Suite 1983/1984 (1 = themes WIP LayeringTest).
- themes (P7 batch 1: storefront foundation + Ecommet; sub-agents sf-catalog/sf-checkout/sf-account done) still running, uncommitted.
- qa-p5 started: browser re-check of finance + new vendor pages, coverage flips.
- themes P7 batch 1 committed 29be999 (full suite 1984/1984): storefront foundation + Ecommet (SF-ECO all except 012), SF-ALL-001..004, ADM-085/085a, decisions #76-#78. Open: Stripe.js CDN exception (#78, needs user), store-own categories on storefront, VEN-030/035 vendor pages, social login, Authorize.Net/2Checkout drivers, MAIL-015.
- elora started: P7 batch 2 Elora ×6 + store-own categories on storefront.
- qa-p5 committed b86e938: finance + vendor polish re-check PASS (suite 2013/2013), 4 bugs fixed (QA-P5-017/018 + 2 low), 26 coverage rows done. Open: QA-P5-019 permissions sync, QA-P5-011 refund store-borne ledger part, VEN-061/061a unchecked, VEN-060b partial.
- fixes started: permissions:sync, QA-P5-011, MAIL-015, table sort-arrow cosmetic.
- fixes committed 3f9ca0f (suite 2020/2020): permissions:sync (QA-P5-019), ledger reconcile (QA-P5-011; run `finance:backfill-ledger --tenant=1` on nogrgr_qa), MAIL-015 review button (partial: Figma 130:10889 unchecked), sort-arrow fix, docs/plan/deploy.md, decision #80.
- vendorp8 started: P8 vendor panel rest (VEN-030 themes, VEN-035 banners, settings, customers, marketing, staff/roles, compliance, VEN-061 invoices).
- elora committed a3e1a12: Elora ×6 (SF-ELO all except 013/P10), store-own categories + badge shelves on all families, decision #79. Filtered suite 330/330 (full suite pending next run). Open: Elora phone v3-v6 not compared one by one, 7px mega-menu overflow (given to souqify).
- souqify started: P7 batch 3 Souqify ×6.
- Client rejected the Stripe.js CDN exception (#78 b updated): storefront Stripe must use hosted Stripe Checkout redirect only. Queued as next small task ("stripe-redirect") when an agent slot frees.
- souqify committed 8f3cea5: Souqify ×6 + inner pages (45 SF-SQF rows), decision #83, Elora/Souqify overflow fixes. Full suite 2101: 3 failures belong to vendorp8's in-progress vendor-themes work (TranslationKeysExist, VendorSetupWizard, VendorOnboarding) — vendorp8 to fix.
- stripe-redirect started: Stripe via hosted Checkout only, remove js.stripe.com, arch test for external scripts.
- stripe-redirect committed ee39a64 (suite 2125/2125). Then client chose option 1: inline Stripe card via Stripe.js from js.stripe.com (only allowed external script), no webhook, like old project. Stripe test keys imported from old dokan_v2 into dev nogrgr + nogrgr_qa (dev DB migrated/seeded non-destructively). Test webhook endpoint created then deleted per client.
- stripe-inline started: restore inline Stripe Elements on checkout + registration + vendor subscription (+ other pay points), arch test exception for js.stripe.com only.
- vendorp8 done (uncommitted, interleaved with stripe-inline WIP): 18 VEN rows (customers/subscribers #81, banners+invoices #82, compliance #84, themes #85, staff & roles #86, VEN-007). Dev + QA migrated, permissions synced. Will commit together with stripe-inline after a full suite run.
- qa-p8 started: browser QA of the new vendor pages.
- qa-p8 PASS (4 fixes, uncommitted). p8-polish started: QA-P8-005..011 + 403 page + client rule "images object-contain, not cover" (arch test).
- QUEUED "fonts": client (2026-11-26) wants exact Figma fonts on admin/vendor panels, central website and each storefront theme family (Ecommet/Elora/Souqify + variants), en + ar; self-hosted via @fontsource / local files (no CDN); currently Outfit + IBM Plex Sans Arabic everywhere.
- Committed 2325db0 (full suite 2170/2172 before fonts; 2 route-allowlist failures fixed): P8 batch 1 + QA-P8 fixes, inline Stripe card (#78b) + USD-only (#87), images contain (#88), Figma fonts per surface (#89: Outfit + Noto Sans Arabic; vendor panel IBM Plex Sans Arabic).
- Open for client: Stripe row shows Apple/Google Pay badges though card-only; General Sans (Tenant Panel labels) needs a self-host OK; Flutter Stripe SDK pending (P11).
- Started: vendorp8b (remaining VEN rows), p9 (affiliates, support, reports, notifications UI, owner pages).
- Committed 8266e12: P8 batch 2 (store pages, appearance, email templates, store SMTP, tracking pixels — client approved keeping pixels) + P9 reports/dashboard/docs (#90) + Support module (#92). Suite 2330: all failures were affiliates WIP (excluded from commit) + module count (fixed). vendorp8c WIP (settings pages) partly included.
- Running: vendorp8c (account/general/currencies/return policy/audit), affiliates (module + lifecycle + admin pages). Next: affiliate panel AFF-*, notifications UI (VEN-087/090, AFF-014, OWN-008), VEN-009x/024/045x analytics, VEN-073 languages, VEN-082 domains.
- Committed 24971c9 (affiliates module + admin #93, vendor account/general/currencies/return policy/audit) and ca28d58 (affiliate panel AFF-001..015, partner program VEN-055/009b, #95). Full suite on tree: 2406/2410, the 4 failures from aff-panel WIP, since fixed by it (77/77 + 149/149).
- Running: notif (VEN-087/090, OWN-008, dashboard widgets, analytics VEN-024/045x), langs-domains (VEN-073 languages purchase, VEN-075 translations, VEN-082 domains). Next: P10 AI, P11 mobile, P12 migration/launch, final QA gate.
