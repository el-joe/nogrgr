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
- **P4 Shipping · P5 rest: cart/checkout, orders, returns, refunds, ledger/settlements/payouts · P6 Central website · P7 Themes · P8 Vendor panel · P9 Affiliates/Support/Content/Reports · P10 AI · P11 Mobile · P12 Migration & launch:** not started.
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
- **commerce** (P5 batch 1): OrderComposer/OrderPricing engine with ORDER_FORMULAS golden + invariant tests; coupons & flash sales central+tenant merge (#65) + admin ADM-025/026; OwnerSupportTest fix.
- Shared test DB: every test command runs under `flock /tmp/nogrgr-tests.lock`. Agents don't commit; orchestrator commits after the batch.
- Next: Language purchases tab (ADM-094) · per-tenant `tenant_static_texts` (#45) · P2 QA gate.
