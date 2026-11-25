# Nogrgr — Master Project Plan

> Owner: PM / Business Analyst · Date: 2026-11-24 · Status: **v1.1 — client decisions applied (see decisions.md); ready for Phase 0**
> Inputs: client brief, `old-system-docs/*.md`, old code `/var/www/multi-vendor`, Figma `2wXSYbP5gQSJHiVQWJJpSf`.
> Companion docs: [01-modules.md](01-modules.md) (module scope & acceptance), [02-architecture.md](02-architecture.md), [03-figma-inventory.md](03-figma-inventory.md), [04-team-and-workflow.md](04-team-and-workflow.md), [decisions.md](decisions.md).

## 1. Product in one paragraph
Nogrgr is a multi-tenant (DB-per-tenant) dropshipping-style marketplace SaaS. The platform owns a **central catalog** (products, variants, stock per branch, shipping). Vendors subscribe to a **plan**, get a store on a **sub-domain or custom domain**, resell central products with their own **markup / overrides** (approved via edit requests), may add **own products**, choose one of **13 storefront themes**, and run their store from the **Vendor Panel**. **Affiliates** earn commission on vendor sign-ups (every vendor is also an affiliate). The platform runs everything from the **Admin Panel**. Customers buy on the **storefront** (web) or a **white-label Flutter app** per tenant.

## 2. Deliverables
| # | Deliverable | Location |
|---|---|---|
| D1 | Admin Panel (central) | `backend` → `{central}/admin` |
| D2 | Vendor Panel (tenant) | `backend` → `{store-domain}/admin` |
| D3 | Affiliate Panel | `backend` → `{central}/affiliate` |
| D4 | Central marketing website (Figma "Central Website" + register flow pages from old project) | `backend` → `{central}/` |
| D5 | Storefront: 13 themes (Ecommet ×1, Elora ×6, Souqify ×6) — home differs per variant, inner pages shared per family | `backend` → `{store-domain}/` |
| D6 | Figma: admin panel layout/components + mobile app designs for 13 themes | Figma file (new pages) |
| D7 | Flutter white-label app, one codebase, built per tenant | `app/` |
| D8 | REST APIs: Mobile API v1 (+ storefront JSON) | `backend` `/api/mobile/v1` |
| D9 | AI features in Laravel (ex-Python): image search, price finder, logo generator, social posts, AI translation | `backend/app/Modules/Ai` |
| D10 | Notification system (database + mail + Reverb) for admins, vendors, affiliates, customers | `backend/app/Modules/Notifications` |
| D11 | Data migration scripts old → new + cut-over runbook | `backend/database/migration-tools`, `docs/plan/data-migration.md` |

## 3. Non-negotiable technical rules (client)
- Laravel 13, PHP 8.3, **Tailwind v4 + vanilla JS / Alpine.js. Never Livewire.**
- **Vite**: every custom CSS/JS file registered as a Vite input; no CDN, no inline scripts/styles (JSON islands only).
- Select2, **Yajra DataTables** (server-side), **intl-tel-input** on every phone input with visitor country pre-selected, Swiper for product rails.
- Architecture: **Repositories, Services, DTOs, Enum classes** (+ FormRequests, Policies, Events).
- Money formulas ported **unchanged** from `/var/www/multi-vendor/docs/ORDER_FORMULAS.md` with the invariant `platform_profit + vendor_profit = order_grand_total`.
- Storefront & central site must be pixel-faithful to Figma, with scroll animations; custom spotlight sliders (`home-pages-custom-sliders.md`) only where Figma shows them.

## 4. Phases & milestones
Each phase ends with a **QA gate** (all Pest + Playwright green, Figma parity check, PM sign-off with client).

| Phase | Scope | Lead roles | Exit criteria |
|---|---|---|---|
| **P0 Foundations** (wk 1–2) | Packages (stancl/tenancy, spatie/permission, yajra, reverb, horizon, openai-php, spatie/translatable), module skeleton, shared kernel (Money, Enums, base Repository/Service/DTO, JSON response contract, tenancy bootstrappers, audit log), CI (Pint, Larastan, Pest, arch tests), Vite inputs, design tokens, `x-ui::*` component library (panel), intl-tel-input/Select2/DataTables wrappers | Tech Lead, DB, FE | Skeleton boots central + one tenant; component gallery page; arch tests enforce rules |
| **P1 Design** (wk 1–4, parallel) | Figma: admin layout + components from Central Website design; missing panel screens; tokens as variables; mobile designs for 13 themes | UI/UX, Graphic | Client approves admin layout (wk 2) and mobile designs (wk 4) |
| **P2 Platform & Identity** (wk 3–4) | Admins & roles/permissions, languages (seed all ISO languages; ar/en default for every tenant; vendor enables/buys others), countries (all + default currency), currencies (+ open.er-api.com sync job), plans, registration + provisioning (sub-domain, custom domain, DNS records), subscriptions, integrations tabs (gateways ×2 configs, mail, Google/Apple, OpenAI…), notification system core | BE, FE, DB | Vendor can register, pay/trial, land in empty vendor panel |
| **P3 Catalog & Inventory** (wk 5–7) | Categories, attributes/variants, products, labels, badges, branches + stock, product requests, edit requests → tenant overrides, brand & manufacturing requests, vendor product import/markup/own products | BE, FE, DB | Vendor sees catalog with overrides; admin approves edit request |
| **P4 Shipping** (wk 6–7) | Fixed shipping per country/gram, shipping rules/zones (weight bands), shipping companies + day rules, stop periods, delivery popup days (sum = 100%), delivery date estimator | BE, DB | Estimator & cost unit-tested against old outputs |
| **P5 Commerce** (wk 8–10) | Cart (DB), checkout, OrderPricing/OrderComposer port, coupons (central + tenant), flash sales (central + tenant), payment gateways (tenant creds), orders lifecycle, shipping list, returns, refunds, settlements/ledger | BE, DB, QA | Formula test-suite parity 100%; full order → refund e2e |
| **P6 Central website** (wk 8–10) | All Figma central pages + register flow, blog, static pages, FAQs, newsletter, SEO | FE, SEO | Lighthouse ≥ 90 perf/SEO; Figma parity |
| **P7 Storefront themes** (wk 9–16) | Section component library; Ecommet; Elora v1–6 homes + shared pages; Souqify v1–6 homes + shared pages; custom sliders; scroll animations; banners; SEO; RTL; **Salla-style Twig theme engine** for vendor custom themes (upload, validate, review, version) | FE, SEO, QA | Each theme passes parity + e2e checkout |
| **P8 Vendor panel complete** (wk 10–14) | All old vendor modules (see 01-modules.md §B) incl. store design, marketing, analytics, finance, settings, staff | BE, FE | Feature-parity checklist ticked |
| **P9 Affiliates, Support, Content, Reports** (wk 12–15) | Affiliate panel + lifecycle, tickets, notifications UI, reports | BE, FE | Parity checklist ticked |
| **P10 AI in Laravel** (wk 13–15) | All 10 old AI actions (01-modules.md §A6c): product / full-store / catalog / static-key / weekly AI translation, price finder, social posts, logo generator, image search, connection test; queued jobs; usage logs; plan limits | BE | Feature tests with faked OpenAI |
| **P11 Mobile** (wk 14–20) | Mobile API v1 parity, Flutter app, per-tenant build pipeline, FCM push | Flutter, BE | 2 pilot tenants built & installable |
| **P12 Migration & launch** (wk 20–22) | Data migration, redirects, reconciliation report, staged cut-over | DB, Tech Lead, QA | Finance reconciliation = 0 diff |

> Timeline assumes the full team working in parallel; it is an estimate, refined at each phase gate.

## 5. Critical path & dependencies
P0 → P2 → P3 → P5 → P7/P11. Design (P1) must stay ≥ 1 week ahead of FE. Shipping (P4) blocks checkout pricing (P5). Mobile API (P11) reuses Commerce services — no duplicate logic.

## 6. Key risks
| Risk | Mitigation |
|---|---|
| Money formula drift from old system | Port old unit tests + worked examples first; golden-file tests from real old orders |
| 13 themes = huge FE surface | Section-component architecture: variants are props + tokens, inner pages shared per family (3 families) |
| Cross-tenant admin lists slow | Central reporting facts (`order_facts`, `ledger_entries`) fed by events |
| Scope creep from "same as old project" | Every old feature listed in 01-modules.md with an explicit in/out decision approved by client |
| Mobile white-label store submission | Build script + per-tenant config; client provides store accounts |

## 7. Definition of Done (every task)
**No page is skipped and nothing ships as "coming soon".** Every page/action of the old system and every Figma frame is listed in [05-page-coverage.md](05-page-coverage.md) and must be fully working (incl. empty/loading/error states) before its phase gate closes.
Code follows CLAUDE.md rules · FormRequest + Policy · Pest tests green · Pint/Larastan clean · ar/en + RTL · responsive · dark/light (panels) · Figma parity checked · notifications wired where the module emits events · reviewed by Tech Lead · QA passed.

## 8. Team & ownership — see [04-team-and-workflow.md](04-team-and-workflow.md)

## 9. Module scope — see [01-modules.md](01-modules.md)

## 10. Client decisions — see [decisions.md](decisions.md) and [01-modules.md §D](01-modules.md)
