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
