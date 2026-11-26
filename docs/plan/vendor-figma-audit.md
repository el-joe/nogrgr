# Vendor panel — Figma audit (Tenant Panel 12:10147 vs our page 60:10147)

Figma file `2wXSYbP5gQSJHiVQWJJpSf`. Audit date 2026-11-26. Rule: decision #101.

- **12:10147 "Tenant Panel Layout"** is the client's vendor-panel design. If a screen exists there, the code follows it.
- **60:10147 "Vendor Panel (new screens)"** is ours. It is only for screens missing from 12:10147, and those screens must look exactly like 12:10147.
- Nothing on 12:10147 or any other client page was edited. All new work is in new frames on 60:10147.
- Screenshots are in `docs/plan/figma-audit/`:
  - `client-*`: client frames
  - `ours-*`: our original frames
  - `new-*`: frames added in this audit

## 1. Inventory of 12:10147 → VEN rows

The page has 73 top-level nodes: 37 screen and modal frames, plus component sets, section labels and loose assets.

| Node | Name in Figma | What it is | VEN row(s) | Notes |
|---|---|---|---|---|
| 12:11267 | Dashboard #3 | Vendor dashboard (desktop) | VEN-009, 009a–f | mobile 12:18171 |
| 12:18171 | iPhone 13 mini - 1 | Dashboard mobile | VEN-009 | |
| 12:12050 | Today's chances | Opportunities | VEN-015 | mobile 12:18958 |
| 12:18958 | iPhone 13 mini - 5 | Today's chances mobile | VEN-015 | |
| 12:14756 | Frame 440 | Select markets modal | VEN-015a | todo |
| 12:14923 | Frame 502 | Select markets: already added | VEN-015b | todo |
| 12:12968 | Products | Central products with markup | VEN-010 | mobile 12:21178 |
| 12:21178 | iPhone 13 mini - 6 | Products mobile | VEN-010 | |
| 12:16200 | Frame 527 | Shipping details modal | VEN-010e | |
| 12:15119 | Frame 442 | AI Advertising Studio / social posts modal | VEN-010c (also VEN-009e entry) | todo |
| **12:13819** | Own products inner page | **"New in products" grid** (cards: Already added, cheaper-than-market, Create your Ad) | **VEN-011 (desktop)** | The coverage doc maps this to VEN-016a, which is wrong: the frame content is the New-in products list. VEN-011 lists only mobile 12:21874. |
| 12:21874 | iPhone 13 mini - 7 | New in products mobile | VEN-011 | |
| 12:13462 | Own products | Own products list | VEN-016 | |
| 12:17880 | inventory Own products | Own products inventory | VEN-016b | mobile 12:23071 |
| 12:23071 | iPhone 13 mini - 9 | Inventory mobile | VEN-016b | |
| 12:17411 | Add product | Own product create / edit form | VEN-016c | mobile 12:22819 |
| 12:22819 | iPhone 13 mini - 11 | Add product mobile | VEN-016c | |
| 12:14144 | Categories | Categories list | VEN-017 | |
| 12:14465 | Categories inner pages | Category products | VEN-017a | mobile 12:22248 |
| 12:22248 | iPhone 13 mini - 8 | Category inner mobile | VEN-017a | |
| 12:17672 | Add Category | Category create / edit | VEN-017b | mobile 12:22624 |
| 12:22624 | iPhone 13 mini - 10 | Add category mobile | VEN-017b | |
| 12:15191 | Orders | Orders list | VEN-020 | mobile 12:23476 |
| 12:23476 | iPhone 13 mini - 12 | Orders mobile | VEN-020 | |
| 12:15544 | Customers | Customers list | VEN-025 | mobile 12:23838 |
| 12:23838 | iPhone 13 mini - 13 | Customers mobile | VEN-025 | |
| 12:15910 | Requests | Requests hub (product / manufacturing / brand tabs) | VEN-050, 050a, 050d, 050g | mobile 12:24211 |
| 12:24211 | iPhone 13 mini - 14 | Requests mobile | VEN-050 | |
| 12:14977 | Frame 441 | Order new product modal | VEN-050b | |
| 12:16096 | Add Request | New manufacturing request | VEN-050e | mobile 12:24527 |
| 12:24527 | iPhone 13 mini - 15 | New manufacturing request mobile | VEN-050e | |
| 12:16223 | order Analytics | Analytics, Order tab | VEN-045, 045a | mobile 12:24731. This frame is also the template for the 045b / 045d tabs. |
| 12:24731 | iPhone 13 mini - 3 | Order analytics mobile | VEN-045a | |
| 12:16815 | Customer Lifetime Value Analytics | Analytics, CLV tab | VEN-045c | mobile 12:25310 |
| 12:25310 | iPhone 13 mini - 4 | CLV mobile | VEN-045c | |
| 12:17282 | Notifications | Notifications list | VEN-090 | mobile 12:24611 |
| 12:24611 | iPhone 13 mini - 2 | Notifications mobile | VEN-090 | |
| 12:25829 | Side menu | Sidebar (component frame) | VEN-006 | |
| 12:18944 / 12:18951 / 12:25854 / 12:25858 / 12:25857 | small frames / assets | Logo lock-ups, store avatar, chips | VEN-006 | |
| 12:40266 | Frame 1 | Component board: buttons, sidebar item, KPI, toggles, pills, dropdown, inputs (LTR + RTL), country chips, mobile nav | VEN-006 / VEN-093 | |
| 12:40267 | Component 1 (15 variants) | Buttons: Default / Hover / Pressed / Disabled / Secondary × S 40 / M 48 / L 56, **radius 0** | all | |
| 12:40334 / 12:40366 | State | Trend pills (+12% / −12%) and status pills (Delivered / Processing / Pending / Shipped), r40 | all | |
| 12:40345 | Dropdown | Opened / Closed (200 px) | all | |
| 12:40379 / 12:40408 / 12:40427 / 12:40437 / 12:40442 | Frame 224 / 102 / 427 / 423 / 409 | Sidebar item, toggles, flash-sale buttons, chips | VEN-006 | Sidebar item = 12:40408 and page-level 12:10990 / 12:10996 |
| 12:40689 | Component 2 | "Complete your store now" CTA (r24) | VEN-009 | |
| 12:40706 | KPI Card (4 variants) | KPI card | all | |
| 12:40753 | Frame 455 | Type scale | VEN-006 | |
| 12:11131 | Nav bar mobile | Mobile bottom nav (Dashboard / Analytics / Appearance / Finance + Store) | VEN-006 mobile | |
| 12:25861 | Desktop - 2 | Alternative "NO GRGR" landing | WEB-031 | dropped (not vendor) |
| 12:26195 | iPhone 13 mini - 16 | Same landing, mobile | WEB-031 | dropped |
| 12:16186–12:16199, 12:17876–12:17879 | Rectangles / TEXT | Section labels: Landing page, Home, Today's chances, Products, Orders, Customers, Requests, Analytics, Notifications | — | |

**Not in 12:10147 (so 60:10147 is legitimate):**
- auth and errors (VEN-001…005, 094…099), onboarding (007 / 008)
- product edit for central products (012 / 012a–e), edit requests (013), sorts (014 / 017c), badges (018)
- order detail and pay platform (021 / 021a–d), returns and refunds (022–024), customer detail (025a–c), newsletter (026)
- all of Your Store (030–037), coupons and flash sales (040 / 041), analytics tabs 045b / 045d, request chats (050c / f / h / i / j)
- partner program (055), finance (060–064), all settings (070–087), support and help (091 / 092), UI kit (093)

## 2. Which Figma node each VEN row was built from

From `05-page-coverage.md` (Figma column), cross-checked against the code in `backend/app/Modules/Platform/resources/views/components/vendor/*`.

| Source | VEN rows |
|---|---|
| 12:10147 (correct) | 009 / 009a–f, 010, 010c, 010e, 011 (mobile only), 015 / 015a / 015b, 016, 016b, 016c, 017, 017a, 017b, 020, 025, 045a, 045c, 050 / 050a / 050b / 050d / 050e / 050g, 090 |
| 60:10147 frames | every other VEN row (001–005, 007 / 008, 010a / b / d, 011a, 012…014, 017c, 018…, 021…026, 030…041, 045b / d, 050c / f / h / i / j, 055…099) |

### Rows that exist in 12:10147 but were built from 60:10147 or our own shell (code-fix batch)

1. **VEN-006 panel shell.** The coverage row cites 12:25829, but the code follows our 60:10147 shell.
   - `sidebar.blade.php` uses the logo with a "VENDOR" tag, grouped items with chevrons and child lists, plus extra top-level items (Finance, Support, Help center, Notifications).
   - `topbar.blade.php` has a sidebar-collapse button, a Ctrl-K search, a locale dropdown and a sun/moon segmented toggle.
   - Client 12:15498 / 12:15522 has:
     - a flat 236 px sidebar with the 8 px left marker and orange active pill, using the `Frame 102` items
     - a 74 px top bar with "Select your country", a #F5F5F5 search, a bell box, a black dark-mode box and a #E7E7E7 store menu
     - page bg #F3F3F3 (ours is #F9F9F9)
   - Fix: re-skin the shell to match the client.
     - Groups become the client's items, with sub-pages listed indented under the active item (see new frame 180:28706).
     - Finance, Support and Notifications move under the client's 11 items or into the store menu.
     - The language switch is a 40 px box in the client style.
   - This affects every vendor page.
   - **Fixed in code (2026-11-26, vendor-shell):** `config/navigation/vendor.php` = the 11 client items in order (client icons as `tp-*` SVGs from 12:15498); a group links to its first reachable sub-page and lists its sub-pages indented only while active (180:28706); Finance pages under Settings; Support / Help center / Notifications / My account / Open store in the #E7E7E7 store menu. Sidebar 236 px with the client logo lock-up (`resources/images/vendor/brand/`), store card, pinned (never collapsed at 1280 and up). Top bar 74 px: Select your country (live-store preview country of the dashboard), #F5F5F5 search (Ctrl/Cmd K palette), language box, bell box, black dark-mode box, store menu. Page bg #F3F3F3; mobile = black r24 bar + #FFF5F3. Screens: `figma-audit/code-before-*` / `code-after-*`.
2. **VEN-011 New central products, desktop.** It must follow **12:13819** ("New in products" card grid with "Already added", price boxes and "Create your Ad"). Code was built from the mobile-only note. Fix the coverage mapping: VEN-011 desktop = 12:13819, VEN-016a = no client frame (needs a 60:10147 frame in client style).
   - **Fixed in code:** `_new-card` follows the 12:13819 card (purple ribbon, Bold name, dark price box + light stock box, preview square + purple full-width CTA). There is no "cheaper than market" figure in the data model, so that chip is not shown.
3. **VEN-090 Notifications.** Code follows 12:17282, which is correct. Our duplicate frame 88:112846 "VEN Notifications inbox" must not be used. Mark it superseded.
4. **VEN-045 Analytics tabs 045b / 045d.** These were built from 87:108835 (our style). They must reuse the 12:16223 / 12:16815 tab layout: the same tab bar, KPI cards and chart cards.
   - **Fixed in code:** one analytics template for all four tabs: Bold 32 title + period / Download report, swipeable pill tabs (`x-ui::tabs variant="pill"`), tab title Bold 24 + subtitle, KPI / chart cards, table card with its title inside.
5. **VEN-012 / 012a–e Product edit (central overrides).** This screen is missing from the client page, but it is the edit form of a product. It must use the 12:17411 "Add Own Product" form language:
   - section cards with title 18 + subtitle
   - filled inputs #F5F5F5 / #EAEAEA r8 with no border
   - "Back to products" outline + "Save product" primary in the header
   - the country checklist style

   Built from 83:100772 / 83:101628 (bordered inputs, tabs, r10 buttons).
   - **Fixed in code:** Bold 32 head with Back to products / Request edit / Add to flash sale outline + Save product primary (48 px), r24 section cards without border / shadow, filled inputs, client tables (`.vnd-table`). The 012b content tab is the Ai module partial (agent ven074) and was not touched.
6. **Buttons everywhere.** Code buttons follow our Button r10. Client `Component 1` (12:40267) is **square (r0)**, Outfit Medium 16, 40 / 48 / 56 heights. Page-level outline buttons on 12:15191 use r8–12 (download report, pagination), so keep r8 only for pagination and the filter icon button.
   - **Fixed in code:** vendor kit in `resources/css/panels/vendor-shell.css`: every `x-ui::button` on the vendor surface is square, Medium 16, md 40 / lg 48 / new `xl` 56, primary #FD6F04 / #E66504 / #8B3D02, secondary = #FD6F04 outline, disabled #787878; icon-only buttons stay r8.
7. **Inputs.** The client uses filled #F5F5F5 (search) / #EAEAEA (form) r8 with no border (12:17411). Our inputs are white with a light grey border, r8.
   - **Fixed in code:** vendor inputs / Select2 / phone are filled #EAEAEA r8 without border (48 high in fields; white + brand line on focus); filters and the topbar search use #F5F5F5. Also re-skinned: page title Bold 32 + 14 #787878 (no breadcrumb on list pages), `x-ui::card` r12 without border / shadow with 24 / 16 titles, `x-ui::kpi-card` = client KPI Card (label 14, value Bold 32, note 12, illustration at the end), DataTables header #F5F5F5 h48 14 #A3A3A3 + zebra #F8F8F8 + #F0F0F0 lines, status pills = client `State` colours (r40, 24 high; Pending text darkened to #C9A800 for legibility).

## 3. Visual drift of 60:10147 frames vs 12:10147

Every batch-2 frame on 60:10147 (82–92:*) uses the same shell and kit, so the drift is systematic:

| Element | 12:10147 (client) | 60:10147 (ours) | Evidence |
|---|---|---|---|
| Page bg | #F3F3F3 | #F9F9F9 | all frames |
| Sidebar | 236 px. Logo image (39 + 105). 11 flat items 48 px with 8 px left marker. Active = #FD6F04 pill to the edge + white text. Store card #F5F5F5 at the bottom of a 780 px white column. | Vendor Sidebar `62:21332`: 264 px, "N" square logo + VENDOR tag, 15 items with chevrons, badges, nested lists, full-height border | `client-12-25829-side-menu.png` vs `ours-62-21332-vendor-sidebar.png` |
| Top bar | 74 px white: country select, search 382 px, bell box, dark-mode box #0B0B0B, store menu #E7E7E7 (General Sans in Figma) | 64 px: collapse icon, Ctrl-K search, EN dropdown, sun/moon pill, bell with count, initials avatar | `client-12-15191-orders.png` vs `ours-84-102416-ven-021-order-detail.png` |
| Page title | Outfit Bold 32 #0B0B0B + Regular 14 #787878, no breadcrumb | SemiBold 24 + breadcrumb | same |
| Cards | white r12, no border, no shadow, padding 16 | white r12 / r14 / r16, 1 px #EEEEEE border on several (3 bordered cards in 90:115176) | 84:102416, 90:115176 |
| KPI cards | `KPI Card` 271 × 145: label 14 / value Bold 32 / note 12 + lottie illustration | icon chip + label + value + sparkline + "vs prev. period" | `ours-90-113836-ai-translation-old.png` |
| Buttons | `Component 1`: square r0, 48 / 56 px, Medium 16 | `Button` 25:10355: r10, 40 px | all |
| Tables | header #F5F5F5 h48, 14 #A3A3A3, zebra #F8F8F8, product thumb 34 px, kebab, client pagination (Previous outline r8, numbered 24 px r4, Next #FD6F04) | `Table Cell` 33:10532, white header with sort icons, no zebra, own pagination | 83:100772, 90:115176 |
| Status pills | `State` r40: Delivered #E6FEE6 / #03AA00, Processing #FFF1E6 / #FD6F04, Pending #FEFAE6 / #F0C800, Shipped #E6E7FE / #424AFF | `Badge` 29:10291, different tints | all |
| Inputs | filled #F5F5F5 / #EAEAEA r8, no border | `Input` 27:10219 white + border | 90:115176 |
| Mobile | black header (r24 bottom), #FFF5F3 bg, horizontally scrolling KPI cards, list rows as cards, `Nav bar mobile` 12:11131 | no mobile frames in batch 2 (only the 3 admin mobile patterns) | `client-12-23476-orders-mobile.png` |

## 4. New frames (all on our page 60:10147)

| Node | Name | Notes |
|---|---|---|
| **180:28706** | VEN-074 / 074a Settings: AI translation — aligned to Tenant Panel — Desktop 1440 | Decision #99: per-language model, no word quota. KPI = Runs / Items translated / Tokens used / Paid languages. Live per-section progress of the current run, with the 10 sections of `StoreTranslationRegistry` and a failed-items notice. Languages card with per-language last run, items and tokens, plus Buy (VEN-074a: language select, $49.00 one-time, Pay & translate). 074a success result banner. Run history table (language, scope full store / product, status, items done, failed, tokens, started, finished, Run again / Retry). "Translate a product" entry (opens the VEN-012b per-product Translate with AI). In section 90:117702, next to 90:113836. |
| **180:29374** | … — Arabic (RTL) — Desktop 1440 | Mirrored layout: sidebar on the right, Noto Sans Arabic as the Figma stand-in for IBM Plex Sans Arabic (#89) |
| **182:29054** | … — Mobile 375 | Client mobile pattern (black header, swipe KPI row, cards, `Nav bar mobile`) |
| 184:29202 | Section "Vendor Panel / Aligned to Tenant Panel …" | at x 6400 on 60:10147 |
| 184:29203 | VEN-021 Order detail — aligned to Tenant Panel | from 84:102416 |
| 184:29780 | VEN-012 / 012a Product edit — aligned to Tenant Panel | from 83:100772 |
| 184:30357 | VEN-022 Returns — aligned to Tenant Panel | from 84:103852 |
| 184:30994 | VEN-076 / 076a Settings: Staff — aligned to Tenant Panel | from 90:115176 (Settings sub-item active) |

All new frames are built from client components where they exist:
- `Frame 102` sidebar items (12:10990 / 12:10996) and client icons
- `Component 1` buttons (12:40267)
- `State` pills (12:40366)
- `Nav bar mobile` (12:11131)

The client's lottie / logo images are reused by image hash. Client nodes were only instanced or read. Nothing was cloned out of them or edited.

The 4 aligned frames first swapped only the shell, cards, buttons and titles. In batch 3 (below) their inputs, tables, tabs and badges were also moved to the new TP kit, so they now follow §3 fully.

## 4b. Batch 3 — new aligned UI kit + aligned frames (2026-11-26)

### UI kit (replaces 92:130313)

| Node | What |
|---|---|
| **191:30162** | Section "Vendor Panel / UI kit — aligned to Tenant Panel", x 12800 on 60:10147 |
| **191:30163** | VEN-093 UI kit — aligned to Tenant Panel — Desktop 1440 (old 92:130313 superseded) |
| VariableCollection 191:30110 | "Vendor · Tenant Panel (12:10147)": 33 colour tokens (bg / brand / text / border / status), radius (0 / 4 / 8 / 12 / 24 / 38), space (4–32), sizes (sidebar 236, top bar 74, controls 40 / 48 / 56). Every TP component binds them. |
| 191:30444 | **TP/Field**: Type Text / Select / Textarea × State Default / Filled / Focus / Error / Disabled, plus Search. Props: Label, Value, Helper, Show helper, Required, Show label. |
| 191:30454 | TP/Checkbox (Off / On) |
| 191:30473 | TP/Status: client `State` look, plus Danger and Neutral tones. Props: Tone, Label. |
| 191:30478 | TP/Tab: the pill tabs of 12:16229 (Active #FD6F04 Medium 16, Idle #DCDCDC 14 #5E5E5E) |
| 191:30489 | TP/Locale tabs (EN / AR) |
| 191:30510 | TP/Icon button (filter 56×48 r8, Primary / Outline) |
| 193:30171 | **TP/Table cell**: Header / Text / Text + sub / Product / Number / Status / Checkbox / Actions. Rows are frames: header #F5F5F5, 1 px #F0F0F0 bottom border, zebra #F8F8F8. |
| 193:30172 | TP/Pagination (12:15482 pattern) |
| 193:30334 / 193:30367 | **TP/Modal** (566, r24, the 12:14923 popup language with r8 buttons) / **TP/Bottom sheet** (375, top r24 + handle). The modal becomes a bottom sheet on mobile. |
| 193:30444 | TP/Toast (Success / Error / Info) |
| 193:30445 | TP/Empty state (client box illustration 12:40631 + Component 1 Mid) |
| 193:30479 / 193:30482 | TP/Page title (Bold 32 + 14 #787878) / TP/Card header (SemiBold 24 + 16 #888580) |

The kit also shows client components instanced as-is: Component 1, Frame 427, KPI Card, State, Frame 224, Frame 102, Frame 423, Frame 409 and Nav bar mobile. Dropdown 12:40345 uses General Sans, which is not installed, so it can only be referenced on the client board.

**Correction to §3 / #101 (read from the nodes):** client form fields (Frame 224, 12:40380) are **#ECECEC, r12, h48, label 14 #787878, required star #E00000, placeholder #C1C1C1**. Focus = white with 1 px #FD6F04. Search and top-bar boxes are #F5F5F5 r8. Table body text is 14 #A3A3A3 (product name 12). Client popups use r24 cards with r8 buttons; page-level buttons stay square `Component 1`.

### How the batch-3 frames were made

Each frame is a copy of its old 60:10147 frame. The copy gets:
- the client shell: sidebar 12:15498 with the matching item active and the section's sub-pages indented under it, top bar 12:15522, bg #F3F3F3, no breadcrumb, Bold 32 title
- every kit instance swapped:
  - Button → `Component 1` (square, icons off)
  - Badge → TP/Status
  - Input / Select / Textarea → TP/Field
  - Table Cell → TP/Table cell, with header / zebra rows
  - Tab → TP/Tab
  - KPI Card → client `KPI Card` (a negative delta uses the `grow down` State)
- card borders and shadows removed

Finance has no client sidebar item, so its pages sit under **Settings** (sub-items: Subscription & balance, Billing & invoices, Payments & payouts). Support and Help have no sidebar item either; they open from the store menu, so no item is active.

Mobile 375 frames reuse the 182:29054 shell: black header with r24 bottom, #FFF5F3 bg, swipeable KPI row and tabs, table rows as cards, two-column blocks stacked, and `Nav bar mobile`.

### New node ids per VEN row (all in section 184:29202 unless noted)

| VEN row | Old frame (superseded) | Aligned desktop | Mobile 375 |
|---|---|---|---|
| 012 / 012a | 83:100772 | 184:29780 (kit updated) | — |
| 012b–e | 83:101628 | **196:30720** | — |
| 021 | 84:102416 | 184:29203 (kit updated) | — |
| 022 | 84:103852 | 184:30357 (kit updated) | — |
| 022a | 84:104582 | **197:30913** | **205:38137** |
| 023 / 024 | 84:105181 | **197:31485** | — |
| 025b | 84:105854 | **197:32195** | **205:38558** |
| 030 / 031 | 85:104756 | **200:32864** | — |
| 032 | 85:105527 | **200:33486** | — |
| 033 / 033a | 85:106165 | **200:34128** | — |
| 034 / 034a | 85:106903 | **200:34905** | — |
| 034b–f | 85:107559 | **200:35591** | — |
| 035 | 85:108271 | **200:36358** | — |
| 036 / 036a / 036b | 85:108862 | **200:36959** | — |
| 037 / 037a | 85:109494 | **200:37622** | — |
| 030a / 030b / 035a (modals) | 85:110108 | **200:38274** | — |
| 040 | 87:107268 | **199:31453** | **205:39102** |
| 041 | 87:108154 | **199:32265** | — |
| 045b / 045d | 87:108835 | **199:32944** (12:16223 tab layout) | — |
| 060 / 060c / 060d | 88:109601 | **199:33682** | **205:39617** |
| 061 / 061a | 88:110393 | **199:34398** | — |
| 062 / 063 / 064 | 88:111108 | **199:35131** | — |
| 070 | 90:111315 | **201:34510** | — |
| 071 | 90:112042 | **201:35177** | — |
| 072 | 90:112623 | **201:35816** | — |
| 073 / 073a / 073b | 90:113194 | **201:36468** | — |
| 074 / 074a | 90:113836 | 180:28706 / 180:29374 (RTL) | 182:29054 |
| 075 | 90:114518 | **201:37173** | — |
| 076 / 076a | 90:115176 | 184:30994 (kit updated) | — |
| 077 / 077a | 90:115822 | **201:37881** | — |
| 078 / 078a / 078b | 90:116465 | **201:38547** | — |
| 079 | 90:117144 | **201:39338** | — |
| 080 / 080a | 91:114835 | **202:36036** | — |
| 081 | 91:115511 | **202:36460** | — |
| 082 / 082a | 91:116087 | **202:36870** | — |
| 083 | 91:116736 | **202:37396** | — |
| 084 | 91:117308 | **202:37788** | — |
| 085 | 91:117954 | **202:38264** | — |
| 086 | 91:118575 | **202:38750** | — |
| 087 | 91:119215 | **202:39204** | — |
| 071a / 071b / 082 add domain (modals) | 91:119876 | **202:39659** | — |
| 091 / 091b | 88:111731 | **206:38046** | — |
| 092 / 092a | 88:112298 | **206:38560** | — |
| Products & AI modals (010a, 010b, 010d, 011a, 012…) | 83:104181 | **206:39026** | — |
| Orders & customers modals (021a–c, 025a, 025c) | 84:107236 | **206:39513** | — |
| Marketing & requests modals (040a, 041a, 041b, 05x) | 87:111915 | **206:39923** | — |
| Finance & support modals (060a, 060b, 091a) | 88:113398 | **206:40420** | — |
| 093 UI kit | 92:130313 | **191:30163** | — |

**Superseded**, kept untouched as history: every "old frame" in the table above, plus 88:112846 (Notifications inbox, replaced by client 12:17282).

## 5. Still to align (60:10147 frames left in the old style)

1. 83:102290 VEN-013 edit requests history
2. 83:102998 VEN-014 / 017c sort
3. 83:103600 VEN-018 badges
4. 84:103244 VEN-021d pay platform
5. 84:106555 VEN-026 newsletter subscribers
6. 87:109541 / 87:110114 / 87:110730 VEN-050c / 050f / 050i / 050j request detail and result
7. 87:111264 VEN-055 partner program
8. 82:* auth, onboarding and errors. Auth and errors have no shell; they only need the TP/Field, Component 1 and bg tokens.
9. Mobile 375 frames for the remaining aligned desktop frames. So far there are 4 (022a, 025b, 040, 060) plus VEN-074.

Known residuals in batch-3 frames. They are fine as a reference but must not be copied literally when coding:
- Old pagination bars (our own pager) were left in a few tables. Code uses TP/Pagination.
- Card titles inside cards kept their old 14–16 SemiBold. The client card title is SemiBold 24 #111111 (TP/Card header).
- Chart cards keep our chart drawing inside a client-style card.

## 6. Font note

The client top bar uses **General Sans** (Regular / Semibold), which is not installed in this Figma environment. The new frames use Outfit there. In code, keep Outfit unless the client supplies General Sans.
