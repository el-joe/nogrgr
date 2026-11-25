# Architecture

## Repo layout
```
backend/   Laravel 13 (all web surfaces + APIs)
app/       Flutter white-label app
docs/plan/ plan, decisions, specs, qa-log
old-system-docs/  reference only
```

## Backend: modular monolith
`backend/app/Modules/<Module>/`
```
Enums/  DTOs/  Models/  Repositories/{Contracts,Eloquent}/  Services/  Actions/
Http/{Controllers/{Admin,Vendor,Affiliate,Storefront,Central,Api},Middleware,Requests,Resources}/
Events/ Listeners/ Jobs/ Notifications/ Policies/ DataTables/
Database/{migrations/central,migrations/tenant,Seeders,Factories}   (class folders capitalised for PSR-4)
routes/{central,admin,affiliate,api,vendor,storefront}.php          (one file per surface, all optional)
resources/views/   (namespace "<kebab-module>::")   resources/lang/{en,ar}   (namespace "<kebab-module>::")
ModuleServiceProvider.php   (extends App\Shared\Modules\ModuleServiceProvider; repository bindings in $bindings)
Concerns/ Support/          (optional: module traits / helpers)
```
Scaffold with `php artisan make:module <Name>` (adds the provider to `config/modules.php`). The base provider auto-loads views, translations, central migrations (`php artisan migrate`) and appends tenant migration folders to `tenancy.migration_parameters.--path` (`php artisan tenants:migrate`).

### Surfaces (routing)
`App\Shared\Routing\Surface` + `SurfaceRouter` mount every module's `routes/<surface>.php`:

| Surface (route file) | Host | Prefix | Route names | Middleware | Guard |
|---|---|---|---|---|---|
| central | primary central domain | `/` | `central.*` | web | – |
| admin | primary central domain | `/admin` | `admin.*` | web, audit.requests | admin |
| affiliate | primary central domain | `/affiliate` | `affiliate.*` | web, audit.requests | affiliate |
| vendor (central host) | primary central domain | `/tenant/{store}` | `vendor-central.*` (internal) | tenant.path, web, vendor.session, audit.requests | vendor |
| api | any (tenant via `X-Tenant`) | `/api/mobile/v1` | `api.mobile.v1.*` | tenant.api, api | sanctum |
| vendor-auth | tenant domain / sub-domain | `/` (`/login`, `/logout`) | `vendor.*` | tenant.web, web, vendor.session, audit.requests | vendor |
| vendor (store host) | tenant domain / sub-domain | `/admin` | `vendor.*` | tenant.web, web, vendor.session, audit.requests | vendor |
| storefront | tenant domain / sub-domain | `/` (customer account `/account/*`, address helpers `/geo/*`) | `storefront.*` | tenant.web, web, customer.session, customer.locale | customer |

### Vendor panel: two host modes, one route set
- **Store host:** `{store-domain}/login`, panel `{store-domain}/admin/...` (`/admin` → `/admin/dashboard`; guests → `/login`).
- **Central host:** `{central}/tenant/login` (+ `/tenant/stores` picker, `/tenant/logout`), panel `{central}/tenant/{store-slug}/...`. `InitializeTenancyByStoreSlug` (Platform) identifies the tenant by `tenants.slug`, removes `{store}` from the route parameters and sets URL defaults, so controllers, middleware, permissions and views are identical in both modes.
- **Links:** always `vendor_route('dashboard', $params)` (helper in `app/Shared/helpers.php`, backed by `App\Shared\Routing\VendorPanel`). It resolves the current mode and store; `VendorPanel::centralUrl($slug, 'dashboard')` builds central-host URLs explicitly (impersonation, emails). Never `route('vendor.…')` (arch test `VendorRouteHelperTest`).
- **Session binding:** every vendor login stores `vendor_panel.tenant_id`; `vendor.session` (`Identity\Http\Middleware\EnsureVendorSessionTenant`) returns 404 when the requested tenant differs (central `/tenant/{other}` or another store's domain), before the user id is resolved. Logout keeps other guards (e.g. the admin session on the central host) signed in.
- **Central login:** `vendor_staff_directory` (central; HMAC-SHA256 of the lower-cased email with the app key → tenant_id, vendor_staff_id), synced by `VendorStaffDirectoryObserver`. Passwords are verified inside each candidate tenant: one match → logged in, several → picker (pending candidates in the session for 5 min), `?store=` hint skips the picker. Same error for unknown email / wrong password; throttle 5 attempts/min per email+IP.
- **Impersonation:** `POST {central}/admin/tenants/{tenant}/impersonate` (`tenants.impersonate`) logs in as the store owner in central-host mode and audits `auth.impersonated` with the admin id.
- **Slugs / sub-domains:** `tenants.slug` is a unique DNS label; `Platform\Support\TenantSlug` + `Rules\ValidTenantSlug` refuse reserved words (`/tenant/*` route words + `config('tenancy.reserved_subdomains')`). Store sub-domains are `{slug}.{config('tenancy.base_domain')}` (`TENANT_BASE_DOMAIN`).

`CENTRAL_DOMAINS` (env, comma separated): the first entry is the primary central domain; the others 301 to it. Panel routes must declare `auth:<guard>` + `permission:<name>,<guard>` (arch test `tests/Feature/Architecture/PanelRoutesTest.php`; guest routes `login`, `password.*`, `register` are allow-listed and must use `guest:<guard>`).
`backend/app/Shared/` — Modules (base provider, `make:module`), Routing (surfaces), Tenancy (provider, `BelongsToCurrentTenant`, `CentralDomains`), Money (`Money`, `MoneyCast`), Http (`Controller`, `JsonResponder`, `GuardRedirector`), DataTables (`BaseDataTable`), Enums (`HasEnumHelpers`: `label()`, `options()`, `values()`), Support, `Repositories/BaseRepository` + `Contracts/RepositoryContract`, `Services/BaseService`, `DTOs/BaseDTO`. Shared never depends on Modules (arch test). Audit log lives in Platform (`Auditor`, `Auditable`, `audit.requests`).

Modules: Platform (tenants, plans, subscriptions, domains, DNS), Identity (admins, vendor staff, roles, affiliates auth, customers), Catalog, Inventory (branches, stock), Shipping, Commerce (cart, checkout, orders, coupons, flash sales), AfterSales (returns, refunds), Billing (gateways, payments, ledger, settlements, payouts, invoices), Affiliates, Storefront (themes, sections, banners, pages, appearance), Content (blog, static pages, FAQs, newsletter), Localization (languages, countries, currencies, translations), Engagement (tickets, requests chats), Notifications, Integrations, Ai, Reporting, Api.

### Auth (all guards)
- Admin / affiliate: `{central}/{surface}/login` (POST `login.store`), `logout`, `forgot-password` (`password.request|email`), `reset-password/{token}` (`password.reset|store`). Customer: the same names under `storefront.account.*` on the store host. One service (`Identity\Services\SessionAuthService`): throttle 5/min per guard+email+IP, inactive accounts refused after the password matched, audit login / logout / failed attempts. Password brokers per guard (`Guard::passwordBroker()`); customers' tokens live in the tenant DB.
- Customer sessions are bound to one store (`customer.session`, key `customer.tenant_id`), the account language is applied/saved by `customer.locale`, post-login `redirect` must pass `App\Shared\Http\SafeRedirect` (same host).

### Customer addresses & countries
- Central `countries` / `cities` (Localization; data in `Localization/Database/Data/*.json`, seeders `CountrySeeder`, `CitySeeder`). Store countries = `Localization\Services\StoreCountries` (active ∩ tenant `selling_country_ids`). Rule `Localization\Rules\StoreCountry`.
- Tenant `customer_addresses` (Identity). One FormRequest `SaveCustomerAddressRequest` for every surface; `CustomerAddressService` owns the default rules and `forCheckout()` (→ `CheckoutAddressSelectionData`, snapshots `OrderAddressSnapshotData` for `orders.shipping_address` / `billing_address`). Pricing country: `Identity\Services\CustomerCountryResolver` (selected address → explicit country → default address → session `storefront.country_id`).
- Reverse geocode / IP location: `Localization\Services\GeocodingService` behind `ReverseGeocoder` / `IpLocator` (config `services.geocoding`), cached, throttle `geocoding`.

### Request flow
`Route → Middleware (tenancy, auth guard, permission) → Controller (thin) → FormRequest → DTO::fromRequest() → Service (business rules, transactions, events) → Repository (queries only) → Model`.
List pages: `index` (Blade shell) + `data` (Yajra JSON) + `store/update/destroy` + `export`.
JSON contract: `{success, message, data?, redirect?}`; 422 `{message, errors}`.

### Tenancy
stancl/tenancy v3, DB-per-tenant (`nogrgr_tenant_{id}`, integer ids). Identification: domain/subdomain (web), `X-Tenant` header (mobile; tenant id in P0, publishable key in P11). Central tables with `tenant_id` use the mandatory `App\Shared\Tenancy\Concerns\BelongsToCurrentTenant` scope (arch test). Provisioning pipeline: create DB → migrate → seed (`Database\Seeders\TenantDatabaseSeeder`), queued when `TENANCY_PROVISIONING_QUEUED=true`; DB pool later. Wiring lives in `App\Shared\Tenancy\TenancyServiceProvider`; models in `Platform` (`Tenant`, `Domain`).

Central vs tenant overrides pattern (coupons, flash sales, banners, product overrides): central table (admin-owned, read-only to vendor) + tenant table (vendor-owned); readers merge both with a `source` = `central|vendor` so formulas know who absorbs the discount.

### Guards
`admin` (central `admins`) · `vendor` (tenant `vendor_staff`) · `affiliate` (central `affiliates`, one per tenant via `tenant_id`) · `customer` (tenant `customers`, web) · `sanctum` customer tokens (mobile, tenant `personal_access_tokens`). Models in `Identity`. spatie/permission tables exist in central and tenant DBs (no teams).

### Testing
Pest, parallel by default (`php artisan test --parallel`; `composer test`). Plain `php artisan test` works too, with no exported env vars.
- **Database:** phpunit.xml forces MySQL `DB_DATABASE=nogrgr_testing` (host/credentials from `.env`); `tests/bootstrap.php` creates it if missing. Parallel workers use `nogrgr_testing_test_{paratest unique token}` and drop their central + tenant DBs on exit; serial runs hold a MySQL advisory lock. `TEST_DB_SUFFIX=x` gives a serial run its own `nogrgr_x_testing`. Explicit override (CI SQLite leg): `TEST_DB_CONNECTION=sqlite TEST_DB_DATABASE=:memory:`.
- **Guard:** the bootstrap and `tests/TestCase.php` abort the run unless the database name ends with `_testing` or contains `_test_` (`Tests\Support\TestDatabaseGuard`), so the `nogrgr` dev DB can never be migrated/wiped by tests. Tenant test DBs are always `nogrgr_tenant_{id}[_{suffix}]_testing` or `..._test_{token}`; the tenancy helper refuses to create/drop any other name.
- **Helpers:** `tests/Concerns/InteractsWithTenancy` (`createTenant()`, `initializeTenancy()`, `tenantUrl()`, `centralUrl()`, `centralPanelUrl()`, `actingAsVendor()`, `createVendorOwner()`). Hosts are pinned in phpunit.xml (`localhost`). Architecture rules in `tests/Feature/Architecture`.

### Real-time & notifications
Laravel Reverb; private channels `admin.{id}`, `admin.broadcast`, `tenant.{tenantId}.staff.{id}`, `affiliate.{id}`, ticket/request chat channels. Every domain event → `NotificationDispatcher` → per-type `Notification` class with channels from user preferences (database, mail, broadcast, FCM for customers). Notification catalog in `docs/plan/notifications.md`.

### Queues
Redis + Horizon: `default`, `mail`, `notifications`, `provisioning`, `ai`, `reports`, `media`. Scheduled: currency rates sync, subscription renewals/reminders, image optimization, cleanup.

## Frontend
- Tailwind v4, tokens per surface: `resources/css/tokens/{base,admin,vendor,affiliate,central,storefront-<family>}.css`. `base.css` maps semantic utilities (`bg-primary`, `text-ink`, `border-line`, `rounded-card` …) to raw `--nog-*` custom properties; surface files (and tenants at runtime) override only the raw values. Dark mode = `.dark` on `<html>` (server-rendered from the `theme` cookie).
- Vite entries (one CSS + one JS per surface): `resources/{css,js}/{admin,vendor,affiliate,central,storefront-ecommet,storefront-elora,storefront-souqify}.{css,js}`.
- JS: ES modules `resources/js/{core,panels/{admin,vendor,affiliate},central,storefront/{core,ecommet,elora,souqify}}`. Alpine.js for small reactivity. Global http client `resources/js/core/http.js` handling 401/403/419/422/429/5xx; UI strings via the `#app-i18n` JSON island (`lang/<locale>/js.php`).
- Layouts (Blade components): `resources/views/components/layouts/{document,panel,auth,central,storefront}.blade.php` → `<x-layouts.panel surface="admin">`.
- Shared panel components `x-ui::*` in `resources/views/components/ui/` (button, field, select2, phone(intl-tel), date, editor, file/image upload, modal→bottom-sheet, datatable, filters, kpi, chart, toast, confirm).
- Storefront: one Blade component per section (`x-sf::section.hero` … in `resources/views/components/storefront/`) with `theme`/`variant` props; theme registry maps 13 themes → family + home layout (list of sections + tokens). Inner pages per family (3 sets).
- Animations: IntersectionObserver reveal utilities (`data-reveal="fade-up"` …), respects `prefers-reduced-motion`; Swiper for rails; `SpotlightCarousel` module for Figma custom sliders.

## Flutter
Feature-first, Riverpod, dio, go_router, freezed. `tenants/<slug>/config.json` (+ icon/splash) → `scripts/build_tenant.sh` generates flavor (bundle id, name, icons) and builds. Theme id picks one of 13 widget themes.
