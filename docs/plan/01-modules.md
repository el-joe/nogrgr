# Module Scope & Acceptance

Legend: **[C]** confirmed by client brief · **[OLD]** "same as old project" (old behaviour = spec, see `old-system-docs/dashboard-*.md` + `module-inventory.md`) · **[NEW]** not in old system, new spec · **[?]** found in old project but not in brief → needs client decision (§D).

## A. Admin Panel

### A1. Catalog
| Module | Tag | Notes / acceptance |
|---|---|---|
| Products | C | CRUD, variants, gallery, translations, countries (`allowed_country_ids` NULL/[]/list), weight (g), cost/sale price, labels, badges, tenant assignment (all / selected = tenant-owned), soft delete/restore, sort, image search |
| Categories | C | tree, featured, thumbnail, translations, sort, products order inside category |
| Attributes (variations + options) | C | build variants (size/color…) |
| Product labels | C | icon, text with placeholders e.g. "Delivered by {delivery_date}" (resolved by delivery estimator), sort, active |
| Product badges | C | lists: new-in, best-selling, featured, recommended, trending-now (+ extensible enum); assign + sort |
| Product edit requests | C/OLD | vendor requests field changes (title, desc, images…); approval writes **tenant override** rows; tenant reads override → fallback to central |
| Product requests | C/OLD | vendor asks for new product; chat + files; status |
| Manufacturing requests | C/OLD | chat, notes, status, payment requests (vendor pays via gateway) |
| Brand requests | C/OLD | same pattern as manufacturing |

### A2. Sales & after-sales
| Module | Tag | Notes |
|---|---|---|
| Orders list + detail | C | filters, export, status, tracking, cancel (reason codes), receipt/label print; own-product orders read-only |
| Shipping list | C/OLD | = old "In-Delivery Orders" fulfilment queue: status, tracking no., attachments, create return |
| Return requests | C/OLD | full state machine, inspection, restock, exchange |
| Refunds | C/OLD | issue, retry, complete, reject; `RefundCalculator` formula |
| Coupons | C | central table (admin, deducted from platform share, read-only in vendor panel) + tenant table (vendor's own, deducted from vendor share); country scope |
| Flash sales | C | same central/tenant split; products + countries + % + dates |

### A3. Shipping & inventory
| Module | Tag | Notes |
|---|---|---|
| Branches | C | CRUD; product/variant stock assigned per branch |
| Fixed shipping costs | C | per country `price_per_gram`; product display price = sale_price + weight×rate (per line, not × qty) |
| Shipping rules / zones | C | rule → country → zones {name, min_weight g, max_weight g, price}; first matching band; none = free; no zone = unavailable |
| Shipping companies | **NEW** (not in old code) | company details (name, logo, contact, tracking URL pattern, active) + day rules per country {min_days, max_days} |
| Shipping stop periods | C | per shipping company; dates/ranges (holidays) skipped when computing delivery dates |
| Delivery popup days | C/OLD | days 1–10 each with %; total must equal 100%; 0% hidden on storefront |
| Delivery date estimator | NEW (service) | inputs: country, company day rules, stop periods → date range for labels/product page/checkout |

### A4. Vendors, plans, money
| Module | Tag | Notes |
|---|---|---|
| Customers (vendors) | C | vendor list + vendor profile (store info, plan, domains, profit %, affiliate commission, stats, orders, compliance) + actions: activate/suspend, assign plan, impersonate (**audited**), edit, delete |
| Plans | C/OLD | term name + icon, price, features, limits, trial days, AI flags |
| Subscriptions | C | registrations & renewals, status, invoices |
| Affiliates | C/OLD | separate table + auth; every tenant auto-gets an affiliate account; accounts, conversions (reverse), payouts, promo codes, program settings |
| Reports | C | see §A7 |

### A5. Content & storefront
| Module | Tag | Notes |
|---|---|---|
| Blog categories / posts | C | central website blog, SEO fields |
| Static pages | C | target: central website / tenant storefronts / both |
| FAQs | C/OLD | |
| Newsletter subscribers | C | list, export, delete |
| Themes | C | 13 themes (Ecommet 1, Elora v1–6, Souqify v1–6): activate, default, country availability, preview image, home sections config |
| Banners | C | admin default banners; vendor adds own, sorts, show/hide admin+own |
| Return policy | C/OLD | |
| DNS records | C/OLD | DNS instructions/templates for custom domains |

### A6. System
| Module | Tag | Notes |
|---|---|---|
| Integrations (tabs) | C | Payment gateways (each: **vendor-payments config** + **tenant-registration config**), Mail, Google login, Apple login, OpenAI, + Pusher/Reverb, FCM, Cloudflare/DNS, tracking defaults |
| Currencies | C | rates from `open.er-api.com/v6/latest/USD` (daily job + manual sync button) |
| Languages | C | ar/en default; add more |
| Countries | C | seeded with all ISO countries + default currency, flag, phone code |
| Notifications | C | inbox page + bell (Reverb) + preferences |
| Support tickets | C/OLD | chat, status, priority |
| Admins & roles/permissions | C | spatie/permission, manage implies view |

### A6b. Languages & translation (client note 2026-11-24)
| Item | Spec |
|---|---|
| Central language list | **Seeder with every ISO 639-1 language** (~184): code, English name, native name, direction (rtl for ar, fa, he, ur, ps, sd, ckb, yi, dv, ug), flag/icon, `is_active`, `is_free`, `price` (USD), `ai_translation_price` (USD), allowed `countries` (null = all), sort. Admin can activate/deactivate, set price/free, edit. |
| Defaults | **ar + en** are free, active and enabled for every tenant automatically at provisioning (cannot be removed; en = fallback locale). |
| Tenant side | Vendor › Settings › Languages lists the admin's *active* languages (except ar/en, already on); vendor enables a free one or buys a paid one (Billing add-on purchase), sets default storefront language, sort, active toggle. Enabled languages appear in the storefront locale switcher and mobile app. |
| Translations storage | spatie/laravel-translatable JSON columns `{en, ar, fr, …}` on every translatable model (central catalog + tenant overrides/own products, categories, pages, banners, settings). UI strings: `lang/*` + tenant overrides table; admin can lock keys. |
| Manual translation | every translatable form field has locale tabs for all the tenant's enabled languages. |
| AI translation | see A6c (product, full store, catalog, static keys). |

### A6c. AI features — port ALL old AI actions to Laravel (no Python)
Module `Ai`: one `OpenAiClient` wrapper (openai-php/laravel) configured from **Admin › Integrations › OpenAI** (encrypted api key, base URL, models: text `gpt-4o-mini`, image `gpt-image-1`, vision `gpt-4o-mini`, search `gpt-4o-mini`, embedding `text-embedding-3-small`, translation `gpt-4.1-mini`; "Test connection"), retries with backoff on 429/5xx, typed errors (quota exhausted 402, auth failed 502, rate limited 429, unavailable 503) → user-friendly messages, token usage logged to `ai_usage_logs` (tenant, feature, model, tokens, cost USD) for limits, billing and reports. All long work runs as queued jobs on the `ai` queue with progress + notifications.

| # | Action (old source) | Where used | New implementation |
|---|---|---|---|
| 1 | **Per-product AI translation** (`ProductAiTranslationService`) | Vendor product & own-product forms: "Translate with AI" from source → target language (name, description, short desc, meta title/keywords/description), preview, regenerate, saved only on Save | `Ai\Services\ProductTranslationService`; gated by plan AI add-on + AI-calls quota; each generation consumes 1 call |
| 2 | **Full-store AI translation** (`TranslateStoreJob`, `StoreTranslatorService`, section jobs) | Vendor › Settings › AI translation: translate settings, categories, products, pages, banners, static UI keys into a tenant language; progress %, summary, tokens, cost | `Bus::batch()` of per-section jobs, brand-context prompt, progress on the tenant language row, notification on finish/fail |
| 3 | **Central catalog translation** (`CatalogTranslatorService`, `TranslateLanguageCatalogJob`) | Admin › Languages: when a new language is added/activated, AI-translate (or copy) the whole central catalog + lang files; progress 0–100% | chained per-model jobs, token tracking per language |
| 4 | **Static key translation** (`TenantTranslationService::translateKey(s)WithAi`, `TranslateStaticKeysJob`) | Admin translation editor + Vendor › Settings › Store translations: translate one key / all missing keys; locked keys skipped | chunked batch job |
| 5 | **Weekly new-product translation** (`products:translate-new`) | Scheduler: products flagged `needs_ai_translation` translated into every paid-AI language per tenant | scheduled command |
| 6 | **AI price finder** (`price_finder.py`, `PriceFinderService`) | Vendor product "AI price" modal: product name (+ image) → avg/median/min/max + market hits | vision identify (optional) → web search (OpenAI web-search tool via search model, + HTTP fetch of shopping pages) → price parsing, outlier trim (>3× median), summary |
| 7 | **AI social posts** (`social_post_generator.py`, `SocialPostService`, `ProductMarketingService`) | Vendor product modal + dashboard "AI ads" widget: captions for instagram / facebook / twitter / linkedin / tiktok / generic / all, in a chosen language, optional generated image | text model captions + image model picture stored on tenant disk; download/copy/share |
| 8 | **AI logo generator** (`logo_generator.py`, `LogoGeneratorService`) | Vendor › Store › Appearance logo builder: store name, tagline, category, style, colors → logo image | image model; per-tenant limit (admin AI logo limit / plan); daily cleanup of unused generated logos |
| 9 | **Visual (image) search** (`image_search.py`, `ImageSearchService`, `EmbedProductImagesJob`) | Admin products search, vendor products search, storefront search-by-image (plan `image_searches_limit`) | vision → description → embedding; vectors in `product_image_embeddings`; job on image upload + backfill command; cosine ranking |
| 10 | **AI connection test** (`AiSettingsPage`) | Admin › Integrations › OpenAI | ping text model |

Limits & billing: plan fields `ai_calls_limit`, `ai_translation_addon`, `image_searches_limit`, `ai_logo_limit`; paid language & AI-translation purchases go through Billing (USD).

### A7. Reports (proposed)
Sales (by period/tenant/country/product/category), platform vs vendor profit, refunds & returns, shipping (by company/zone, delivery performance), coupons & flash-sale impact, subscriptions/MRR/churn/trials, affiliates (clicks→conversions→payouts), vendor leaderboard, product performance (views, add-to-cart, conversion), customers (new/returning by tenant), AI usage. All from central fact tables; CSV/XLSX export.

## B. Vendor Panel (tenant) — rebuild of old modules [OLD + improvements]
**Access (client 2026-11-24):** the vendor panel opens from the tenant's own domain/sub-domain (login `{store-domain}/login`, pages `{store-domain}/admin/...`) **and** from the central domain (`{central}/tenant/{store-slug}/...`), with a central login + store picker at `{central}/tenant/login` — e.g. `nogrgr.com/tenant/login`, `vendor.nogrgr.com/login`. Storefront customers use `/account/login`. Same controllers, permissions and views in both modes; cross-store access returns 404.

Dashboard (KPIs, owed-to-platform alert, setup progress, live store preview by country, suggestions) · Products (import central, markup per country/variant, stock/weight overrides, edit requests, AI price, AI social posts, AI translation, sort) · Own products · Categories · Badges · Orders (detail, cancel, manual refund, pay platform) · Returns · Refunds · Return analytics · Customers · Your Store (themes per country, home variant, page builder, pages, appearance incl. AI logo, banners) · Coupons · Flash sales (+ read-only admin ones) · Analytics · Product/Manufacturing/Brand requests (chat + pay) · Partner program (SSO to affiliate) · Finance (subscription & renew, invoices, settlements, payouts received) · Settings (account, general, currencies, languages, AI translation, staff, roles, payment gateways own creds, return policy, email templates, SMTP, domains + DNS check, tracking pixels, compliance) · Notifications · Support · Help docs · Onboarding wizard.
Added in rebuild: audit log, deny-by-default permissions on every route, newsletter subscribers page.

## C. Affiliate Panel [OLD + fixes]
Register (pending) / login / **password reset** / SSO · dashboard · links + promo codes · conversions · payouts (request, min) · profile (payout details) · **notifications** (new). Commission only on the referred vendor's first paid subscription; reversed on refund.

## D. Decisions on old-project modules (client: "build all of them, place them professionally")

| Old feature | Lives in module | Where in UI |
|---|---|---|
| Compliance / KYC | Platform › Compliance | Admin › Vendors › Compliance; Vendor › Settings › Compliance center |
| Wallet & finance: ledger, settlements, payouts, transactions, invoices, payment logs | Billing (double-entry ledger) | Admin › Finance (Ledger, Settlements, Payouts, Transactions, Invoices, Payment logs); Vendor › Finance |
| Finance reports, orders report, return analytics | Reporting | Admin › Reports (tabs) |
| Pending registrations | Platform › Registration | Admin › Vendors › Pending registrations |
| Tenant change requests (countries/categories) | Platform › Requests | Admin › Requests center (with product/brand/manufacturing/edit requests) |
| Domain requests + DNS records | Platform › Domains | Admin › Vendors › Domains (tabs: Requests, DNS records) |
| Email templates (central + tenant) | Notifications › Templates | Admin › Settings › Email templates; Vendor › Settings |
| Translation files editor + key locks | Localization | Admin › Localization › Translations |
| Paid languages + AI-translation purchases | Localization + Billing (add-on purchases) | Admin › Localization › Languages; Vendor › Settings › Languages |
| Payment gateway limits | Billing › Gateways | Admin › Integrations › Payment gateways › Limits tab |
| Maintenance windows, cache tools, dev docs | Platform › System | Admin › System |
| Vendor custom themes (old ZIP Blade) | **Storefront › Theme Engine** — Salla-style: sandboxed **Twig** themes (theme.json manifest, templates, settings schema, locales, assets), upload ZIP or CLI, static validation, admin review queue, versioning, no PHP execution | Vendor › Store › Custom themes; Admin › Themes › Review queue |
| Tenant owner portal (one owner, many stores) | Identity › Owners | `{central}/my-account` |
| Path storefront `/vendor/{slug}` | dropped; replaced by `{central}/preview/{slug}` for theme preview | — |
| Public REST API v1 | Api › Public (per-tenant API keys, not tenant id) | Vendor › Settings › API keys |
| Neozena importer | Catalog › Import (generic external-catalog import driver, Neozena first) | Admin › Products › Import |
| AI logo limit, image-search limit | Platform › Plans (plan limits) + per-tenant override | Admin › Plans; Admin › Vendor profile |
| Today's chances | Catalog › Opportunities | Vendor › Products › Opportunities (has Figma 12:12050) |
| Order receipt / shipping label | Commerce › Documents | Admin & vendor order detail |
| Tenant mobile-app settings | Api › Mobile | Admin › Vendor profile › Mobile app; Vendor › Store › Mobile app |
| Sort products/categories, image search, tenant-assigned products | Catalog | Admin › Products |

### Resolved defaults (PM decisions, change anytime)
- Shipping company per order = **default company per country** (admin can reassign on the order). Delivery estimate = that company's day rules for the country, skipping its stop periods.
- Central website = Figma page **12:41054 "Central Website"** (orange #FF522C). The "NO GRGR" landing is not used.
- Admin "Customers" = vendors (brief) **plus** a read-only "Shoppers" tab across tenants from central facts.
- Image search: OpenAI vision → text-embedding, vectors stored in MySQL, cosine ranking in a queued service (pgvector/Meilisearch later if catalog > 100k images).
- Mobile store accounts: platform account by default; vendor's own account supported in build config.
- Payment gateways at launch: COD, Stripe, PayPal, Paymob, Tap, MyFatoorah, Moyasar, Tabby, Tamara; remaining old drivers in P9 behind the same contract.
- Souqify extra homes (alternate, Christmas): post-launch seasonal skins, not part of the 13.

## E. Still open (non-blocking)
None blocking Phase 0. New questions go here.
