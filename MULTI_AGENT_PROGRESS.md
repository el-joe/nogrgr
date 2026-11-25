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
| P2 Localization | Languages (184 ISO, one price/lang #45), Currencies (+ daily rate sync), Countries admin; tenant language services; 88 tests | `d27aab1` |
| Verify | Recovered WIP verified: migrate:fresh clean, suite **660/660**; repository bindings added; QA-P0-016 and CustomerAuthTest confirmed OK | `e794206` |

## Pending (by phase)

- **P2 Platform & Identity (rest):** services, controllers, FormRequests, DTOs, panel UI + DataTables for admins/roles, languages, countries, currencies, plans, tenant provisioning, subscriptions, integrations, notifications settings; tests.
- **P3 Catalog & Inventory (rest):** repositories, services, controllers, panel UI, imports, media conversions, inventory; tests.
- **P4 Shipping · P5 Commerce · P6 Central website · P7 Themes · P8 Vendor panel · P9 Affiliates/Support/Content/Reports · P10 AI · P11 Mobile · P12 Migration & launch:** not started.
- **P1 Design:** Figma additions as needed per module (never edit existing client frames).

## Dependencies

Critical path: **P0 → P2 → P3 → P5 → P7 / P11**.
- P3 needs P2 (currencies, languages, tenants, permissions).
- P4 needs P2 countries; P5 needs P3 + P4 + `Commerce\Pricing`.
- P6/P8/P9 need P2 + P3; P7 needs P5; P10 needs P3; P11 needs the Mobile API (after P5); P12 last.
- QA-P0-008/009 deferred parts (Google sign-in, country filter, Export report) wait for P2/P9.

## In progress (batch P2-1, started 2026-11-25, agent team of 2)

- **identity** (started after localization finished): admin profile, notifications inbox + preferences, admins, roles/permission matrix, audit log (ADM-005, 007/007a, 110/110a, 111/111a, 112/112a).
- **billing**: Plans CRUD, Subscriptions (state machine, expiry job, assign plan), Invoices (list/detail/print), Payment logs (list/detail/refund event), add-on purchase service.
- Shared test DB: every test command runs under `flock /tmp/nogrgr-tests.lock`. Agents don't commit; orchestrator commits after the batch.
- Next: Vendors/provisioning/domains/compliance (after billing) · Integrations (AI translation provider switch #43) · Language purchases tab (ADM-094) · per-tenant `tenant_static_texts` (#45) · P2 QA gate.
