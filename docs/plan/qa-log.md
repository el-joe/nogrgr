# QA Log

Defects found by QA. Severity: **Critical** (blocks use / gate), **High** (feature broken or rule violated), **Medium** (wrong behaviour with workaround), **Low** (cosmetic / parity).
Status: open · fixed (QA, test-side) · fixed (dev) · verified.

---

## Phase 0 gate — 2026-11-25

Environment: `backend/` on main (uncommitted working tree), PHP 8.3, MySQL. Live check on `http://127.0.0.1:8010` (`php artisan serve`, `CENTRAL_DOMAINS=127.0.0.1`) against the throw-away database `nogrgr_qa_testing` (migrated + seeded, dropped afterwards). Built-in browser at 1440 / 768 / 375, light + dark, en + ar. Figma references: Dashboard `41:12632`, Login `55:20387` (read-only screenshots).

Gate commands: `php artisan test --parallel` 238/238 passed before QA additions (27.6 s); 452/454 after (32.3 s, 2 failures = QA-P0-002 and QA-P0-005). `composer lint` passed (Pint + PHPStan 0 errors). `npm run build` passed (0.8 s, one INEFFECTIVE_DYNAMIC_IMPORT warning for `panels/ui/confirm.js`).

| ID | Area | Severity | Status |
|---|---|---|---|
| QA-P0-001 | Panel shell / sidebar JS | Critical | verified |
| QA-P0-002 | UI kit / data table | High | verified |
| QA-P0-003 | Admin + affiliate sign-in | High | verified |
| QA-P0-004 | UI kit / responsive 375 | Medium | verified |
| QA-P0-005 | i18n / validation messages | Medium | verified (one leftover string: QA-P0-014) |
| QA-P0-006 | Dashboard / Arabic numerals | Low | verified (regression: QA-P0-012) |
| QA-P0-007 | Panel shell / role label | Low | verified |
| QA-P0-008 | Dashboard / Figma parity | Low | verified, partly deferred (country filter + Export report wait for P2/P9 routes) |
| QA-P0-009 | Login / Figma parity | Low | verified, partly deferred (Google sign-in waits for P2 integrations; footer note text differs) |
| QA-P0-010 | Config / app name | Low | verified |
| QA-P0-011 | Arch test / no-placeholder (Arabic) | Medium | fixed (QA, test-side) |
| QA-P0-012 | Login / copyright year | Low | verified |
| QA-P0-013 | UI kit / data table search keeps page offset | Medium | verified |
| QA-P0-014 | i18n / error-toast summary | Low | verified |
| QA-P0-015 | Storefront motion / slider a11y labels | Low | open (storefront scope, assigned to frontend, not counted for P0) |
| QA-P0-016 | Data table / reload after a confirmed row action | Low | open |

### QA-P0-001 — Whole panel is inert below 1024 px (tablet + mobile unusable)
- **Area:** `resources/js/panels/ui/sidebar.js` `setMode()`.
- **Steps:** 1. Sign in as admin. 2. Open `/admin` or `/admin/ui-kit` at 768 or 375 width. 3. Click the menu button, a theme button or any control.
- **Expected:** Drawer opens (288, scrim); every control works.
- **Actual:** Nothing reacts to pointer or keyboard. `<html>` carries `inert=""`. `setMode()` sets `html.dataset.sidebar = mode` and then looks up `document.querySelector('[data-sidebar]')`, which now matches `<html data-sidebar="drawer">` before the `<aside>`. `toggleAttribute('inert', mode === 'drawer')` therefore makes the whole document inert in drawer mode. A programmatic `.click()` opens the drawer, a real click does not. At ≥ 1280 the mode is `expanded`, so desktop is unaffected.
- **Fix hint:** Select the sidebar element with a selector that cannot match `<html>` (e.g. `aside[data-sidebar]` or a separate `data-sidebar-root`), or store the mode in another attribute.
- **Screenshot ref:** live check 768 × 1024 and 375 × 812 (`/admin`, `/admin/ui-kit`); `document.documentElement.hasAttribute('inert') === true`.

### QA-P0-002 — UI kit data table returns 500 on every load
- **Area:** `app/Modules/Platform/DataTables/UiKitProductsDataTable.php:26`, `UiKitController::table()`.
- **Steps:** Open `/admin/ui-kit` and scroll to "Data table" (or `GET /admin/ui-kit/table?draw=1&start=0&length=10&search[value]=&search[regex]=false`).
- **Expected:** 200 with rows; paging, status filter, search and bulk select work.
- **Actual:** 500 `ErrorException: Array to string conversion`. DataTables always sends `search` as an array (`search[value]`, `search[regex]`); the controller passes `$request->only(['status', 'search'])` and the table casts it with `(string)`. The UI shows the "Something went wrong / Retry" state, so paging, filter and bulk select could not be exercised live. The existing test only calls the endpoint without `search`, so it passed.
- **Test:** `tests/Feature/Platform/UiKitAccessTest.php` › "accepts the standard DataTables search parameter shape (QA-P0-002)" (fails until fixed).
- **Screenshot ref:** live check 1440, ui-kit › Data table; console `Failed to load resource: 500`.

### QA-P0-003 — Admin and affiliate "Sign in" buttons are dead
- **Area:** `app/Modules/Identity/routes/admin.php`, `affiliate.php`, `Admin\LoginController` ("Phase 0 placeholder … POST lands in P2"), `identity::auth.login` (`action="{{ $action ?? '#' }}"`).
- **Steps:** Open `/admin/login`, enter any credentials, click "Sign in".
- **Expected:** The form authenticates (or shows the validation / failed-login error); the user menu offers "Sign out".
- **Actual:** The form posts to `#` → `POST /admin/login` answers **405**. Same for `/affiliate/login`. No admin/affiliate logout route either, so the user menu has no "Sign out". This breaks the DoD rule "no dead links/buttons" and the P0 exit "skeleton boots" for the admin surface. QA had to sign in for the live check with a session created server-side.
- **Screenshot ref:** live check `/admin/login` 1440; `curl -X POST /admin/login` → 405.

### QA-P0-004 — Horizontal overflow on the component library at 375 px
- **Area:** `/admin/ui-kit` page layout (`platform::admin.ui-kit`), dropdown popovers.
- **Steps:** Open `/admin/ui-kit` at 375 × 812 (QA-P0-001 must be worked around to interact).
- **Expected:** No horizontal scroll at phone width (16 px gutter).
- **Actual:** The layout viewport grows to 430 px. Every `section.rounded-card` is 384 px wide (right edge 400). A closed `.ui-popover` (`start-0 top-full`, 200 px) reaches x = 430. Text and swatches are clipped on the right, and the bottom sheet renders 430 px wide and is cut off. `/admin` (dashboard) at 375 has no overflow.
- **Screenshot ref:** live check 375, ui-kit top and "Open modal" bottom sheet.

### QA-P0-005 — Validation messages are English in the Arabic UI
- **Area:** `lang/ar/` (no `validation.php`; only auth, js, navigation, ui). No custom attribute names either.
- **Steps:** Switch to Arabic and submit the ui-kit form empty.
- **Expected:** Arabic 422 messages with translated attribute names.
- **Actual:** "The name field is required." and so on, also in English with raw attributes ("The title.en field is required.", "The start date field is required.").
- **Test:** `UiKitAccessTest` › "returns translated 422 messages in Arabic (QA-P0-005)" (fails until fixed).

### QA-P0-006 — Mixed digit systems on the Arabic dashboard
- **Steps:** `/admin?lang=ar`.
- **Expected:** One numeral system per locale (decide Latin vs Arabic-Indic; design-specs §7 keeps numbers LTR).
- **Actual:** Store count, KPI deltas and "Needs attention" counts use Arabic-Indic `٠`. KPI values, chart axis and dates use Latin `0`, `26`, `2026`.

### QA-P0-007 — Role label not translated
- **Steps:** `/admin?lang=ar`: look at the sidebar footer and the user menu.
- **Expected:** Arabic role name.
- **Actual:** "Super Admin" (from `Str::headline($user->getRoleNames()->first())` in `x-ui::user-menu`).

### QA-P0-008 — Dashboard parity with Figma 41:12632
- **Actual vs Figma:** The header has only the date range. "All countries" and "Export report" are missing. KPI cards have no "⋯" menu, and the comparison text is truncated ("vs previous 30 …" where Figma shows "vs prev. 30d"). The revenue card has no 7D/30D/12M segmented control or menu. "Needs attention" rows have no chevrons. "Platform health" has no "Currency rates" row. With empty data, the revenue chart Y axis shows 0–1. Items that depend on routes that don't exist yet may be hidden on purpose (no dead links). Confirm this with PM/Design and list them in 05-page-coverage.md.

### QA-P0-009 — Login parity with Figma 55:20387
- **Actual vs Figma:** "Forgot password?" is missing (its route doesn't exist), the "or" divider and "Continue with Google" are missing, and the footer note reads "Sign-ins are recorded in the audit log" where Figma shows "2FA required · IP allow-list enforced". The stat chips show live counts (0 / 0 / 2 languages) instead of stores / themes / gateways. Layout, gradient panel, card, fonts and language switch match.

### QA-P0-010 — Browser titles end with "· Laravel"
- **Area:** `.env.example` `APP_NAME=Laravel`, `config/app.php` default `'Laravel'`.
- **Expected:** "Nogrgr".

### QA-P0-011 — No-placeholder arch test missed Arabic phrases (fixed by QA)
- **Area:** `tests/Unit/Architecture/NoPlaceholderContentTest.php`.
- **Actual (before):** `'soon' => 'قريباً'`, `<span>قريبًا</span>`, "قادم قريباً", "تحت الإنشاء", "متوفر قريباً", "لوريم إيبسوم" and "نص تجريبي" all passed. The pattern `قريب[اًا]?` consumed only one of the two letters in "اً", so even a bare "قريباً" was never caught.
- **Fix:** Patterns moved to `tests/Support/PlaceholderContent.php` and extended (both tanween spellings, تحت/قيد + الإنشاء/التطوير/البناء, لم يتم تنفيذه بعد, Arabic lorem / dummy text, `coming-soon` / `under_construction` with hyphens/underscores). The test now has positive and negative datasets ("ستصلك طلبيتك قريبًا" stays allowed). The repository scan is still clean.

---

## Phase 0 gate re-test — 2026-11-25 (after dev fixes)

Gate commands:
- `php artisan test --parallel`: 607/607 passed, 2182 assertions. It took 120 s wall clock, against 32 s before; the machine was shared with a concurrent frontend task.
- `composer lint`: Pint passed, PHPStan 0 errors.
- `npm run build`: passed in 0.7 s, no warnings.

Review of the test change backend made: `->withCredentials()` in `UiKitAccessTest` › "returns translated 422 messages in Arabic (QA-P0-005)" is legitimate. Laravel's `postJson()`/`getJson()` send cookies only when `withCredentials` is set (`MakesHttpRequests::prepareCookiesForJsonRequest()`), so before the change the `locale` cookie never reached the app. The test is now stronger: it asserts the exact Arabic messages for `name`, `title.en` and `title.ar`. The QA-P0-002 test is unchanged.

Live check:
- Server: `127.0.0.1:8010`, `nogrgr_qa_testing`, migrated and seeded; the DB was dropped afterwards and the server stopped.
- Sign-in: through the real admin form with a factory admin whose password was generated. A wrong password shows the generic error; the right one lands on `/admin`. Sign out from the user menu returns to `/admin/login`, and `/admin/ui-kit` then redirects to the login. Forgot password works in en and ar, and the affiliate login has a real POST action.
- Widths and themes: 1440, 768 and 375; light and dark; en and ar.
- Re-checked defects:
  - **001:** `<html>` is no longer inert; only the closed `aside` is. At 768 a real click opens the drawer: 288 px, from the inline-end side in RTL. Esc closes it and returns focus to the menu button.
  - **002:** the table loads (200). Paging works (`start=10`). Search works when started from page 1 (see 013). The status Select2 filter works and is synced to the URL. Bulk select-all selects 10 rows, and the bar's Activate sends POST /bulk → 200, shows the toast and clears the selection.
  - **004:** at 375, `scrollWidth` equals 375 and the bottom sheet is exactly 375 wide.
  - **005:** Arabic field messages, including the attribute names.
  - **006:** one digit system (Latin).
  - **007:** "مشرف عام".
  - **008:** KPI menus, "vs prev. 30d", the working 7D/30D/12M switch and the Currency rates row are now present.
  - **010:** titles end in "· Nogrgr".
- Console: no JS errors apart from the expected 422 from the validation demo.
- `/admin/ui-kit/storefront-motion` at 375, RTL: no horizontal overflow. Dragging the rail moves it in the RTL direction, both ways. Enter on Next advances it. With focus inside the rail, ArrowLeft goes to the next slide, ArrowRight to the previous one, and End jumps to the last.

### QA-P0-012 — Copyright year shows as "2,026"
- **Area:** `app/Modules/Identity/resources/views/auth/_panel.blade.php:36`. It runs `Number::format(now()->year, …)`, which adds a thousands separator. This is a side effect of the QA-P0-006 fix.
- **Steps:** Open `/admin/login` (en or ar) and read the footer of the gradient panel.
- **Expected:** "© 2026 Nogrgr · Admin console".
- **Actual:** "© 2,026 Nogrgr · Admin console" and "© 2,026 نقرقر · لوحة الإدارة". The affiliate and vendor sign-in pages share this partial.
- **Severity:** Low.

### QA-P0-013 — Searching from page 2 shows an empty table ("Showing 11–3 of 3")
- **Area:** data table JS (`x-ui::datatable` search / filters handling).
- **Steps:** 1. `/admin/ui-kit`, data table, go to page 2. 2. Type "linen" in the search box.
- **Expected:** A new search or filter resets to page 1 and shows the 3 matching rows.
- **Actual:** The request keeps `start=10` (`draw=3 … start=10&search[value]=linen`). The table body is empty, the footer reads "Showing 11–3 of 3" and the pager shows page 1. No empty state appears. Reloading with `?search=linen` shows the 3 rows correctly.
- **Severity:** Medium. Every list page will use this component.

### QA-P0-014 — Error toast summary suffix not translated
- **Steps:** `/admin/ui-kit?lang=ar`, submit the form empty.
- **Expected:** An Arabic summary.
- **Actual:** "حقل الاسم مطلوب. (and 8 more errors)". Laravel's `(and :count more error[s])` strings come from JSON translations, and `lang/ar.json` does not exist.
- **Severity:** Low.

### QA-P0-015 — Slider navigation labels are English in Arabic (storefront motion demo)
- **Steps:** `/admin/ui-kit/storefront-motion?lang=ar`. Inspect the rail's previous/next buttons.
- **Actual:** `aria-label="Previous slide"` / `"Next slide"`. These are Swiper's default a11y messages and are not passed from `js.php`.
- **Severity:** Low. This is storefront JS (P7), so it is not counted against the P0 gate.

**Re-test verdict: PASS.** All Critical, High and Medium P0 defects from the first run are verified fixed. Tests, lint and build are green. Open items to carry into P1/P2: QA-P0-013 (Medium, data-table UX), plus QA-P0-012, 014 and 015 (Low), and the deferred parts of 008 and 009.

## PM fixes 2026-11-25 (awaiting QA verification)
- QA-P0-013 — fixed (dev): `resources/js/panels/datatable.js` — `datatable:reload` (fired by filters/search) now resets paging to page 1 unless `detail.keepPage`; retry/bulk reloads keep the current page.
- QA-P0-012 — fixed (dev): `identity::auth._panel` footer year printed as a plain integer (no grouping separator).
- QA-P0-014 — fixed (dev): new `backend/lang/ar.json` with Arabic plural forms for Laravel's "(and :count more error(s))" summary + "The given data was invalid."; covered by `tests/Feature/Ui/ArabicValidationSummaryTest.php`.
- QA-P0-015 — assigned to frontend (storefront motion i18n).
- QA-P0-016 — fixed (dev): `panels/ui/confirm.js` row-action reload now passes `detail.keepPage` so the table stays on the current page.

---

## Targeted verification of the PM fixes — 2026-11-25 (final P0 gate)

Commands:
- `npm run build`: passed in 0.9 s, no warnings.
- `composer lint` (Pint + PHPStan): passed, 0 errors.
- `php artisan test --parallel`: run twice. Each run gave 610/611 (2188 assertions, about 120 s). The single failure is in `tests/Feature/Identity/CustomerAuthTest.php`; it was a different test in each run. That suite covers the storefront account pages, which a separate frontend task is still writing: the files are untracked and the response shows a raw key, `storefront::account.welcome_back`. It is outside Phase 0 scope and not counted. The P0 storefront check, "serves customer auth under /account" in TenantSurfacesTest, passes.

Live check: `127.0.0.1:8010` against `nogrgr_qa_testing`, signed in through the real admin form. Afterwards the server was stopped, the DB dropped and the local test credential file deleted.
- **QA-P0-013 — verified.** All checks were made from page 2 ("Showing 11–20 of 36"):
  - Typing "linen" requests `start=0` and shows "Showing 1–3 of 3" with 3 rows.
  - Choosing status Draft requests `start=0&status=draft` and shows "Showing 1–7 of 7", all rows Draft, with the filter synced to the URL.
  - The search "zzzz" combined with Draft shows "Showing 0 results" and the "No results match your filters" empty state with "Clear filters" and a filter chip.
  - Select-all followed by the bulk action Activate gives the toast "Action applied to 10 rows.", clears the selection, and reloads with `start=10`, so the table stays on page 2.
  - Retry: no error could be triggered live. In code, the `[data-dt-retry]` handler calls `api.ajax.reload(null, false)`, which keeps the page.
- **QA-P0-012 — verified.** The footer now reads "© 2026 …" on `/admin/login`, `/affiliate/login` and `/tenant/login` (central-host vendor sign-in), in both en and ar.
- **QA-P0-014 — verified.** Submitting the ui-kit demo empty in Arabic shows the toast "حقل الاسم مطلوب. (و8 أخطاء أخرى)".

### QA-P0-016 — A confirmed row action sends the table back to page 1
- **Area:** `resources/js/panels/ui/confirm.js:117`. After a confirmed request it dispatches `datatable:reload` without `detail: { keepPage: true }`. Since the QA-P0-013 fix, that event resets paging.
- **Steps:** On any list, go to page 3 and use a row action that opens the confirm dialog with `data-confirm-method` (for example, delete a row). Confirm it.
- **Expected:** The table reloads on page 3, the same as after a bulk action or Retry.
- **Actual (from the code):** The table reloads on page 1. No list page has row actions of this kind yet, so this is not visible in P0.
- **Severity:** Low. Fix: `new CustomEvent('datatable:reload', { detail: { keepPage: true } })`.

**Final Phase 0 gate verdict: PASS.**
- No Critical, High or Medium defect is open.
- All Phase 0 tests, lint and build are green.
- Still open: QA-P0-015 (Low, storefront, assigned to frontend), QA-P0-016 (Low), and the deferred parts of 008 and 009 (Google sign-in, country filter, Export report), which wait for P2/P9.

---

## P2 gate (P2 + P3 batches 1–2 + texts batch) — 2026-11-25

Setup: dedicated DB `nogrgr_qa` (+ tenant DB suffix `_qa`), `php artisan serve --port=8099`, Playwright. Walked: admin (all P2/P3 pages, en/ar, light/dark, 1440 and 375 px), the central sign-up on the trial path (sign-up → plan → verify → complete → store → provisioning), the owner portal `/my-account`, and a provisioned store's storefront `__st` texts.

**Results**
- `php artisan test --parallel`: 1327/1327 at the start. Re-run after fixes: 1373/1374. The one failure (`CatalogTranslatableTest`, `CatalogImportCategoryMap`) comes from the uncommitted P3 batch 3 work by the requests teammate. It is out of scope and that teammate has been told.
- `npm run build`: passed at the start. It now fails because `panels/admin/index.js` imports `./requests.js` and `./catalog-import.js`, which batch 3 has not created yet (requests teammate told).
- `pint --test`: clean for the gate scope; only 2 batch 3 files fail.
- PHPStan: **52 errors**; P0 was 0. Mostly type noise (`nullsafe.neverNull`, collection generics, `getCollection()` on the paginator contract, `$skus_count`/`$created_at` magic properties). The real bug it caught, an undefined `$invoices` in `AddonPurchaseRepository` search, was fixed by texts.
- Browser: no 500s, no JS errors and no raw translation keys on any page in scope. RTL is correct.
- Storefront static texts: the tenant DB holds 196 rows (98 per locale). A DB value change shows on `/account/login` after `StaticTextResolver::forget()`.
- Figma: the vendor profile (72:49758), integrations (51:17971), owner My stores (122:102288) and products list (44:13636) match in layout and tokens. The deviations are listed below.

**Fixed in this gate**
- QA-P2-001 (Medium): at 375 px, the integrations, gateway edit, role create/edit and vendor profile Domains tab scrolled sideways by 260–310 px. Cause: `sr-only` (absolute) checkboxes inside `overflow-x-auto` without a positioned ancestor, plus nested grids without `min-w-0`. Fix: `relative` on `overflow-x-auto` wrappers (including `x-ui::datatable`) and `*:min-w-0` on the nested grids. Re-checked on 22 pages: no horizontal scroll.
- QA-P2-002 (Medium): product create/edit showed the browser "Leave site?" prompt after a **successful** save. `http.js` follows `payload.redirect` before `ajax:success` clears the dirty flag. Fix: `panels/admin/products.js` lets the guard stand down while a submit is in flight. It could not be re-checked live because the build is blocked (see above).
- QA-P2-003 (Low): with the sign-up e-mail empty, the error read "required when provider is not present". It now shows the standard required message. Regression test added in `SignUpFlowTest`.

**Open**
- Medium: QA-P2-004, the PHPStan gate is red (52 errors, see above). Assign to the module owners.
- Low: QA-P2-005, WEB-018a store step has no categories picker (Figma 133:12577). Marked partial; due with `tenants.selling_category_ids`.
- Low: QA-P2-006, retrying a provisioning run that failed mid-migration fails with "Table 'permissions' already exists". DDL is not transactional, so the half-made tenant DB has to be dropped by hand. Found because the QA run used a DB cache store, but any mid-migration failure leads here.
- Low: QA-P2-007, Figma deviations:
  - Gateway logo tiles are all dark brown; the design uses brand colours.
  - The owner sidebar badge "STORE OWNER" wraps to two lines; the design shows "OWNER" on one line.
  - The owner shell has no "Help & support" link.
  - DataTable filter selects use the default Select2 triangle instead of the chevron.
  - The products list has no bulk-select column.
  - The vendor Overview has no KPI row or sales chart. These belong to P9 (ADM-041e).
- Low: QA-P2-008:
  - Opening `admin/catalog/labels/create` and `admin/system/admins/create` directly renders a bare modal fragment with no layout.
  - The sign-up stepper on `/register/verify` and `/register/complete` does not mark finished steps.
  - The owner "Add domain" modal shows an empty "Add these records" list when no DNS templates exist.

**Coverage:** 100 reported rows plus ADM-092/093/094 checked. All are `done` except WEB-018a (`partial`). The ADM-093 and ADM-094 route names were corrected to `.index`.

**Verdict: PASS.** No Critical or High issue is open, and every test in the gate scope passes. Before P3 closes: QA-P2-004 (PHPStan) and the batch 3 build/test breakage.

## P2 fixes (teammate "fixes") — 2026-11-26 (awaiting QA verification)
- QA-P2-004 (PHPStan): 52 → 0 outside the in-progress P3 batch 3 files. The fixes are real types, with no baseline and no ignores: a `TenantCollection` generics stub, the concrete `LengthAwarePaginator` from repositories, `@property` docs for aggregates and notification columns, `array-key` maps in `CountryPriceTable`, and `->` for `x?->y ??`. The requests teammate owns the remaining errors, all in its new import and requests-center files.
- QA-P2-006: `PartialMigrationCleaner` drops the tables a failing tenant migration had already created. The run therefore resumes at that migration on retry, and seeders stay `findOrCreate`. Regression test: `ProvisioningTest` "resumes a run that crashed in the middle of a migration".
- QA-P2-008: remote-modal URLs opened directly (labels, admins, audit event, product prices, owner domain edit) render the full panel page (`RendersRemoteModals`) and return to the list after a save. On verify and complete, the sign-up stepper marks plan and payment as done. The owner "Add domain" modal falls back to `tenancy.custom_domain_records` when no DNS templates exist, or shows an empty-state line.
- QA-P2-005: the WEB-018a store step has a "Store categories" Select2 multi-select with search: active top-level categories, ar/en, required while any exist. The choice is stored on `vendor_registrations.selling_category_ids` and then `tenants.selling_category_ids`.
- QA-P2-007: gateway tiles use brand-colour tokens (`--gateway-<code>`). The owner tag reads "Owner"/"المالك" on one line (`whitespace-nowrap`). The owner sidebar gets "Help & support", which links to the new `owner.support` page (OWN-009): channels come from General settings, with an empty state when none are set. The Select2 chevron replaces the triangle globally: the skin moved to `shared/select2.css` and the vendor `<b>` is hidden. The products list gets a bulk-select column with activate, deactivate and delete via `ProductService::bulk`, and delete asks for confirmation. Vendor Overview KPIs are left for P9.

---

## P3 / P4 / P5 (batches 1–3) / P6 + vendor panel gate (teammate "qa2") — 2026-11-25

**Setup.** QA DB `nogrgr_qa`, rebuilt with `migrate:fresh --seed`; tenant DBs use the `_qa` suffix. Cache is `file` (own prefix), queue is `sync`, mail is `log`. Server: `php artisan serve --port=8099 --no-reload`. Without `--no-reload`, `serve` drops the shell env and silently uses the dev `.env`.

Data was built through the UI:
- Admin catalog: categories (including the tree drag), an attribute, a product with 2 variants, branch stock.
- Branch, fixed costs SA/EG, a shipping company, a KSA rule.
- Stripe sandbox config with placeholder test keys, coupon QA10, central flash sale.

Stores:
- QA store 1: real sign-up, trial path, then the 4-step setup wizard.
- QA store 2: `TenantProvisioningService`.

Both stores imported all central products. Owner role re-synced on both stores (`TenantPermissionSeeder`): 44/44 vendor permissions.

**Results**
- Tests: the first run had 1824/1841, and all 17 failures came from finance's half-written `GatewayRefundUpdates` binding (QA-P5-008). The final run, with this gate's new tests, is **1861/1861 passed** (`--parallel --processes=12`).
- `npm run build`: passes. `pint --test`: passes. PHPStan: **0 errors**.
- Browser (Playwright):
  - 127 admin, 29 vendor and 26 central URLs, each in en/light/1440 and ar/dark/375. Detail pages were included.
  - No 500s, no JS errors, no raw keys and no horizontal/RTL overflow after the fixes below.
  - Only 404s: `/pages/about` (system page served at `/about`) and the intended 404 page.
- Flows exercised end to end:
  - Sign-up → plan (trial) → verify mail → account → store (categories picker) → provisioning.
  - Owner portal and the central login that lands in the vendor panel.
  - Impersonation: one-time link; re-use gives **410**; banner; End session returns to the admin vendor page.
  - Vendor login, reset mail and onboarding wizard.
  - Import all; card grid; product edit; price-list markup run (queued, 100 %); shipping details; edit request ER-1.
  - Admin approve.
  - Product request PR-0001 with chat in both directions.
  - Store API: cart → coupon → checkout options → place (Stripe, idempotency key) → order list.
  - Payment simulated with `markGroupPaid` (no real gateway).
  - Shipping queue packed → in transit (company, tracking) → delivered.
  - Admin create return → approve → received → inspect + restock → refund. Refund list: reject, complete.
  - Vendor 2-step cancel; admin coupon and flash sale with product picker; contact form; newsletter.
- **Decision #65 verified.**
  - Store 1's approved edit request shows "QA Phone X — Store One Edition" on store 1 only.
  - Store 2 and the central product still read "QA Phone X".
- **Money (ORDER_FORMULAS §1–3), checked by hand on stored orders:**
  - ORD-10001 (2 lines, 1.3 kg, no promos): items 300.37, shipping 7.50, total 307.87. Platform 258.64 + vendor 49.23.
  - ORD-10003 (central 10 % flash + central 10 % coupon):
    - price 286.61, fixed shipping 5.00, discount 29.16, line total 262.45, coupon 26.25, total 243.70.
    - Platform 195.93 = cost 238.84 + fixed 5 − flash 29.16 + shipping 7.50 − coupon 26.25. Vendor 47.77 = profit.
  - Return value of 1 of 2 units = 134.50. Vendor card and price-list prices match cost × markup + per-country fixed shipping.
- Figma (read-only):
  - Vendor products grid 12:12968: matches after the fix.
  - Vendor orders 12:15191: matches; KPI icons are line icons instead of illustrations.
  - Admin order detail 49:17280: matches.

**Fixed in this gate**
- QA-P5-001 (High): the vendor products card grid (VEN-010/011/016) collapsed every card to 56 px, so titles were cut and images and prices hidden.
  - Cause: the card-grid overrides sat in `@layer components` and lost to the unlayered `.ui-datatable … td { height: 56px }` in `panels/base.css`.
  - Fix: the overrides moved out of the layer in `resources/css/panels/vendor-catalog.css`.
- QA-P5-002 (Medium): raw keys `notifications::panel.inbox.vendor_empty_description` and `mark_all_vendor` on the vendor inbox. They were nested under `inbox.columns` in en/ar `panel.php`.
  - Fixed. New guard: `tests/Feature/Architecture/TranslationKeysExistTest.php` checks that every literal `module::key` used in code exists in en and ar.
  - Regression test in `VendorNotificationsTest`.
- QA-P5-003 (Low): admin and vendor notification inboxes printed `:data-filter-label="__('…')"` literally, because Blade binding was used on a plain `<input>`. Fixed in `Notifications/resources/views/{admin,vendor}/index.blade.php`.
- QA-P5-004 (High): refund Retry / Mark completed / Reject (admin ADM-024, vendor VEN-023) posted to `/admin/[object HTMLInputElement]` and got a 404.
  - Cause: the hidden `name="action"` field shadows `form.action` in the shared `core/ajax-forms.js`. The handler now uses `getAttribute('action')`.
  - This also fixes finance's payout and ledger forms. Test: `tests/Unit/Architecture/AjaxFormsActionTest.php`.
- QA-P5-005 (Medium): at 375 px the shipping rule form scrolled sideways by 183 px.
  - Cause: grid items without `min-w-0`, and `overflow-x-auto` wrappers without `relative` (the `sr-only` escape, same as QA-P2-001).
  - Fixed in the rule form and zone partial. `relative` added to the 7 remaining wrappers: vendor product edit, import preview and categories, language purchase, stop periods, company day rules, shipping settings.
- QA-P5-006 (Medium): the filters-bar search input kept its intrinsic width. At 375 px it ran off-screen, on the left in RTL, on card-grid lists. Fixed with `w-full min-w-0` in `components/ui/filters-bar.blade.php`.
- Regression: `ProvisioningTest` now asserts that a new store's owner holds every `PermissionCatalog::tenant()['vendor']` permission.

**Found and fixed by the owning teammate (finance)**
- QA-P5-007 (High, money): one return could be refunded twice.
  - Cause: `ReturnRequestService::maxRefund()` did not subtract the return's own pending, failed or completed refunds. RMA-1001 got 2 × $134.50.
  - Fix: `RefundService::maxForReturn()`. Test: "never refunds one return twice".
- QA-P5-008: 17 suite failures (`BindingResolutionException` for `GatewayRefundUpdates`) caused by batch 4 work in progress. Now bound in the AfterSales provider.

**Open**
- Medium: QA-P5-009, P3 vendor pages not built.
  - VEN-017 (vendor categories, inner page, create, sort) and VEN-018 (badges, products, sort): no routes exist.
  - Not verified for lack of data: VEN-011a, VEN-015a/b, VEN-016a, VEN-050f/i, ADM-033d.
  - Left `todo` in coverage.
- Low: QA-P5-010, vendor orders list "Your share" shows the stored `vendor_profit` for cancelled orders (ORD-10002 shows $68.90), while the detail shows $0.00 (§3: cancelled = 0). "Collected" ignores refunds.
- Low: QA-P5-011, after a seller-fault return refund on a platform-collected order, the store share goes negative (−$85.27, §3 "partial refund reduces the vendor's share only"), but "Owed to platform" shows $0.00. The debt is not surfaced. For finance (ledger).
- Low: QA-P5-012, setup-wizard "Default country" offers all 249 countries instead of the countries just picked.
- Low: QA-P5-013, the city seeder has no cities for the UAE (and other countries), so a UAE branch can't be created (city is required).
- Low: QA-P5-014, on a gateway edit, enabling a config and toggling "Active" before saving gives "Enable vendor payments … before activating it" (422). Save first; consider disabling the toggle while the form is dirty.
- Low: QA-P5-015, the admin product form tab strip overflows at 1440 px with a scrollbar ("Stock" cut off).
- Low: QA-P5-016, the provisioning page's "Contact support" falls back to `no-reply@<host>` when no support e-mail is set.
- VEN-007 stays partial: the wizard's Theme step waits for the themes page (P7).

**Coverage:** 97 rows set to `done`:
- All 38 `done (unverified in browser)` rows.
- ADM-031…035 except 033d; ADM-080…084.
- VEN-010/010a/010e, 011, 012/a/c/d/e, 013/a, 014, 015, 016/b/c, 050/a/b/c/d/e/g/h.
- WEB-001/002/005–011/013–015/018a/025–027.

`partial`: VEN-007 (reason updated), VEN-009 (unchanged).

**Verdict: PASS.** No Critical or High issue is open; both Highs found were fixed and have regression tests. The full suite, build, Pint and PHPStan are green. Open: QA-P5-009 (Medium, vendor categories and badges VEN-017/018 not built, plus a few unexercised modals) and the Lows above.

## P5 finance + vendor polish re-check (teammate "qa-p5") — 2026-11-26

Scope: commits 55ef85a (finance) and cb8e8e7 (vendor polish). **Setup:** QA DB `nogrgr_qa` (tenant suffix `_qa`), `php artisan serve --port=8095 --no-reload`, file cache with its own prefix, sync queue. Headless Chromium driven by Playwright scripts. Every page was checked at 1440 en/light and at 375 ar/dark for HTTP status, JS errors, raw keys, horizontal overflow and placeholders. The server was stopped afterwards.

**Pages (all PASS after the fixes below)**
- Admin: ledger list (store balances) and per-store ledger (ADM-050/050a). Printable statement; it opens the print dialog on load. Mark-settlement-paid modal with a PNG proof. Settlements list, detail and proof download (ADM-051). Payouts: create, edit to paid, cancel a scheduled payout, cancel a paid payout (ADM-052/052a/052b). Transactions list, detail and CSV export; ledger CSV export (ADM-053). Coupon and flash-sale create modals; gateway edit/list; product form.
- Vendor:
  - Money flow: purchases owed, settlement payments and payouts received tabs, plus the settlement detail.
  - Coupons and flash sales: the central read-only tab with "Hide from my store"; own coupon and flash sale created through the modals. VEN-041b: Smart Watch attached to the store's sale from the product page.
  - Categories: list, create (with image, 422 on empty), inner products page with the add-product modal, edit, visibility toggle (own and central), delete.
  - Sort page, Categories tab (move + save persisted). Badges: add products, add a whole category, reorder + save, remove.
  - Subscription & balance: overview, history and transactions tabs; change-plan and renew modals. "Online payment unavailable" state shown with no `tenant_registration` gateway. The gateway error message shows when Stripe fails with placeholder keys.
  - Setup wizard Countries step.
- QA-P5-012 (wizard countries), QA-P5-014 (gateway Active switch disabled with a "save first" hint) and QA-P5-015 (tab strip scrolls without a scrollbar, with edge fades) are verified fixed.
  - QA-P5-012: the default-country select only lists the picked countries and follows a newly added chip.

**Money (ORDER_FORMULAS §1–3, by hand)**
- ORD-10003 (central 10 % flash + central 10 % coupon, platform-collected). Marked paid via `markGroupPaid`.
  - Price 286.61 + fixed 5.00 − flash 29.16 = line 262.45. Coupon 26.25. Total 243.70.
  - platform 195.93 = 238.84 + 5 − 29.16 + 7.50 − 26.25; vendor 47.77. 195.93 + 47.77 = 243.70.
  - Ledger TX-88003 credits `vendor_payable` 47.77 (= vendor_profit, §3 platform_own_vendor).
- ORD-10901 (a vendor-collected/COD clone of ORD-10001, total 307.87).
  - Ledger TX-88004 debits `vendor_receivable` 258.64 (= platform_profit, §3 vendor_own_platform).
  - A settlement of 100.00 left 158.64 owed, with the same figure on the admin ledger, the vendor money flow and the subscription page.
- Payouts:
  - Available amount = payable 47.77. Over-limit input gets a 422.
  - Paid PO-882 posted a 27.77 debit; cancelling it posted the reversal, back to the same balance. A scheduled payout cancelled with no ledger entry.

**Fixed in this pass**
- QA-P5-017 (Medium): every empty tab of a list scoped by a hidden `tab`/`status` filter showed "No results match your filters" + "Clear filters" instead of its own empty state and create button. Affected: vendor coupons and flash sales, payouts, settlements, returns, refunds, orders, shipping.
  - Fix: `resources/js/panels/ui/filters.js` `hasActiveFilters()` ignores unlabelled hidden scope fields.
  - Test: `tests/Unit/Architecture/EmptyStateFixedScopeTest.php`. Browser re-checked: "No coupons yet" + New coupon.
- QA-P5-018 (Low): in Arabic, KPI card amounts rendered as "$US ٤٧,٧٧" because ICU puts bidi marks in the string and the `<bdi>` resolved to RTL. Fixed with `<bdi dir="ltr">` in `resources/views/components/ui/kpi-card.blade.php`. Test in `tests/Feature/Ui/DisplayComponentsTest.php`.
- Ledger statement (Low): the table had no cell padding ("Debit CreditBalance", date touching the entry) and the card shrank to its content.
  - Fix in `Billing/resources/views/admin/finance/ledger/statement.blade.php`: `w-full`, `px-2` cells, and a horizontal-scroll wrapper on phones.
  - Assertion added to `FinanceLedgerTest`.
- Vendor badges (Low): the store group heading read "your badge" (the lower-case row note). It now uses the new key `catalog::taxonomy.badges.store_group` ("Your products" / "منتجاتك"). Assertion added to `StoreTaxonomyTest`.

**Open**
- QA-P5-019 (Medium, deploy): new permissions such as `catalog.categories.*`, `catalog.badges.*` and `finance.subscription.*` never reach existing stores.
  - Repro: QA store 1, provisioned before cb8e8e7, got **403** on `/admin/catalog/categories` and `/admin/catalog/badges` until `tenants:seed --class=TenantPermissionSeeder` was run by hand.
  - Cause: the owner role is synced only by the tenant seeder at provisioning. There is no deploy step or command for live stores, and the central `CentralPermissionSeeder` has the same gap.
  - Suggest: a `permissions:sync` command (central + every tenant) in the deploy script, or a tenant migration per permission batch.
- QA-P5-011 is still open. The RF-5001 return refund (134.50) on platform-collected ORD-10001 reversed only the vendor share (49.23). The 85.27 §3 says the store bears ("partial refund reduces the vendor's share only") is not in the ledger.
- QA-P5-010 not re-checked.
- Notes:
  - Right-aligned DataTable columns show their sort arrow at the start of the cell, which looks like an empty sortable column. This is cosmetic and applies to all panels.
  - Duplicate "Electronics" root categories come from QA/demo seed data, not code.

**Tests:** full suite `--parallel --processes=12` **2013/2013 passed** (24213 assertions). `npm run build` passes; Pint passes on the changed files.

**Verdict: PASS** for the re-checked scope. No Critical or High issue; QA-P5-019 (Medium, permission sync on deploy) is open.

**Coverage:** 26 rows set to `done`: ADM-050, 050a, 051, 052, 052a, 052b, 053; VEN-017, 017a–c, 018, 018a, 018b, 040, 040a, 041, 041a, 041b, 060, 060a, 060c, 060d, 062, 063, 064. VEN-060b is `partial`: the paid and cancelled result pages are covered only by Pest, because QA has no working subscription gateway. VEN-061/061a are untouched.
