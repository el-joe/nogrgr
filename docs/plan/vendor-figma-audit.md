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
2. **VEN-011 New central products, desktop.** It must follow **12:13819** ("New in products" card grid with "Already added", price boxes and "Create your Ad"). Code was built from the mobile-only note. Fix the coverage mapping: VEN-011 desktop = 12:13819, VEN-016a = no client frame (needs a 60:10147 frame in client style).
3. **VEN-090 Notifications.** Code follows 12:17282, which is correct. Our duplicate frame 88:112846 "VEN Notifications inbox" must not be used. Mark it superseded.
4. **VEN-045 Analytics tabs 045b / 045d.** These were built from 87:108835 (our style). They must reuse the 12:16223 / 12:16815 tab layout: the same tab bar, KPI cards and chart cards.
5. **VEN-012 / 012a–e Product edit (central overrides).** This screen is missing from the client page, but it is the edit form of a product. It must use the 12:17411 "Add Own Product" form language:
   - section cards with title 18 + subtitle
   - filled inputs #F5F5F5 / #EAEAEA r8 with no border
   - "Back to products" outline + "Save product" primary in the header
   - the country checklist style

   Built from 83:100772 / 83:101628 (bordered inputs, tabs, r10 buttons).
6. **Buttons everywhere.** Code buttons follow our Button r10. Client `Component 1` (12:40267) is **square (r0)**, Outfit Medium 16, 40 / 48 / 56 heights. Page-level outline buttons on 12:15191 use r8–12 (download report, pagination), so keep r8 only for pagination and the filter icon button.
7. **Inputs.** The client uses filled #F5F5F5 (search) / #EAEAEA (form) r8 with no border (12:17411). Our inputs are white with a light grey border, r8.

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

The 4 aligned frames swap the shell and restyle cards, buttons and titles. Inner widgets (inputs, tables, tabs, badges) still use our kit. They are a correct reference for shell, page head and cards. Inputs and tables should follow §3 when coded.

## 5. Still to align (60:10147 frames left in the old style), by usage

1. 83:101628 VEN-012b–e product edit tabs
2. 84:104582 VEN-022a return detail
3. 84:105181 VEN-023 / 024
4. 84:105854 VEN-025b
5. 87:107268 / 87:108154 coupons and flash sales
6. 87:108835 analytics 045b / d (use 12:16223)
7. 88:109601 / 88:110393 / 88:111108 finance
8. 85:104756 … 85:110108 Your Store
9. 90:111315 … 90:117144 settings 1 (except 074 and 076)
10. 91:* settings 2
11. 88:111731 / 88:112298 support and help
12. 83:102290 / 83:102998 / 83:103600 / 83:104181, 84:103244, 84:106555, 84:107236, 87:109541 … 87:111915, 88:113398
13. 82:* auth, onboarding and errors. Auth and errors have no shell; they only need the button, input and bg tokens.
14. 92:130313 UI kit. Rebuild it as the client kit: 12:40266 plus the missing pieces.

Superseded: 88:112846 (Notifications inbox), replaced by client 12:17282.

## 6. Font note

The client top bar uses **General Sans** (Regular / Semibold), which is not installed in this Figma environment. The new frames use Outfit there. In code, keep Outfit unless the client supplies General Sans.
