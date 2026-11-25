# Nogrgr — project rules for every agent

Multi-tenant marketplace SaaS rebuild. Read `docs/plan/00-master-plan.md` first, then the module you work on in `docs/plan/01-modules.md`.

- `backend/` Laravel 13 (see `backend/CLAUDE.md` for Laravel Boost rules) · `app/` Flutter · `old-system-docs/` + `/var/www/multi-vendor` = old system, **reference only**.
- **Never Livewire.** Blade + Tailwind v4 + vanilla JS / Alpine.js. Every CSS/JS file is a Vite input. No CDN, no inline script/style (JSON islands allowed).
- Select2 for selects, Yajra DataTables (server-side) for lists, intl-tel-input for every phone field (visitor country pre-selected), Swiper for product rails.
- Layers: Controller (thin) → FormRequest → DTO → Service → Repository → Model. Enum classes for every status/type. Code lives in `backend/app/Modules/<Module>`; shared code in `backend/app/Shared`.
- Money: base currency is **USD** (all stored amounts; other currencies are display-only conversions). Integer minor units via `App\Shared\Money\Money`; formulas exactly as `/var/www/multi-vendor/docs/ORDER_FORMULAS.md`; never re-implement pricing outside `Commerce\Pricing`.
- Every panel route has a permission/policy. Central tables with `tenant_id` use the tenant scope.
- All user-facing text translatable (ar/en), RTL supported. Panels support dark/light.
- Domain events → notifications (database + mail + Reverb) via the Notifications module.
- Tests (Pest) for every endpoint and all pricing/shipping logic. Run tests with `php artisan test --parallel` (`composer test`); every test must be parallel-safe (no fixed DB/file/cache names — use the ParallelTesting token). Run `vendor/bin/pint --dirty` before finishing.
- **Every page is fully built — no "coming soon", "under construction", stub or empty pages, no dead links/buttons, no lorem ipsum.** Every old-system page/action and every Figma frame must exist in the new project (track in `docs/plan/05-page-coverage.md`). A feature is only done when all its pages, states (empty, loading, error) and actions work. Enforced by `tests/Unit/Architecture/NoPlaceholderContentTest.php`.
- **Never change the client's existing designs.** On Figma pages Ecommet, Tenant Panel Layout, Central Website, Elora Variants and Souqify Variants: no edits, moves, renames, restyles, detaches or deletes (not even to fix mistakes). Only ADD new pages/frames; existing themes and the tenant panel design are implemented exactly as designed.
- **Storefront themes must feel premium and alive:** professional scroll/entrance animations, micro-interactions and swipe on every rail (touch on phones AND mouse-drag/trackpad on desktop) per `docs/plan/07-motion-guidelines.md` — layered on top of the Figma design without changing it.
- Match Figma file `2wXSYbP5gQSJHiVQWJJpSf`; check the node with the Figma MCP before building UI.
- Record architecture/dependency decisions in `docs/plan/decisions.md`.
