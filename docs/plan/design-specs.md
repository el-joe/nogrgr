# Design Specs — Admin Panel (Phase 1, part 1)

Figma file `2wXSYbP5gQSJHiVQWJJpSf`. All admin work is on the page **"Admin Panel"** (`23:10147`). No existing page or frame was changed.
Mobile-app screens for the 13 themes are a later part. They will be added to this file under a separate heading.

Design language: the Central Website (`12:41054`) gives the brand values: primary `#FF522C`, ink `#41251E`, gray `#717171`, subtitle `#707070`, placeholder/tint `#FFF2EF`, and the font Outfit. The layout ideas (sidebar, KPI cards, tables) come from the Tenant Panel (`12:10147`).

**How you tell the admin panel from the vendor panel:**
- **Admin:** dark sidebar (`#41251E`) with an orange (`#FF522C`) active pill. Rounded controls (radius 10). KPI cards are white with an icon chip. Only the main metric uses a gradient card.
- **Vendor:** white sidebar with an orange `#FD6F04` pill. Square buttons. Full-gradient KPI cards.

---

## 1. Tokens (Figma variables)

There are two collections. Code syntax is `var(--ng-…)` (for example `color/brand/primary` → `var(--ng-brand-primary)`, `space/16` → `var(--ng-space-16)`).

| Collection | Id | Modes | Notes |
|---|---|---|---|
| Admin Primitives | `VariableCollectionId:23:10148` | Value | Raw palette. Scopes are empty, so these values never show in property pickers. |
| Admin | `VariableCollectionId:23:10188` | Light `23:1`, Dark `23:2` | Semantic colors (aliases of the primitives), spacing and radius. |

### 1.1 Primitives
| Scale | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 |
|---|---|---|---|---|---|---|---|---|---|---|
| brand | #FFF2EF | #FFE3DB | #FFC6B6 | #FFA189 | #FF7A5B | **#FF522C** | #E8401B | #C23113 | #7A2A17 | **#41251E** |

Neutral (warm): `0 #FFFFFF · 50 #FAF8F7 · 100 #F4F1F0 · 200 #E9E4E2 · 300 #D6CFCC · 400 #A9A09D · 500 #717171 · 600 #5C5552 · 700 #3F3835 · 800 #2A2421 · 850 #211C1A · 900 #1A1513 · 950 #110D0C`

| Status | 50 (bg light) | 400 (fg dark) | 500 (fg light) | 950 (bg dark) |
|---|---|---|---|---|
| green (success) | #E8F7EE | #34C26A | #16A34A | #0E2A1A |
| amber (warning) | #FEF5E6 | #F5A524 | #D98A06 | #33240A |
| red (danger) | #FDECEC | #F05252 | #DC2626 | #3A1212 |
| blue (info) | #EAF1FE | #4F8BF5 | #2563EB | #111F3D |

### 1.2 Semantic colors (Light / Dark)
| Token | Light | Dark | Use |
|---|---|---|---|
| color/bg/canvas | neutral/50 #FAF8F7 | neutral/950 #110D0C | page background |
| color/bg/surface | #FFFFFF | neutral/900 #1A1513 | cards, modals, topbar |
| color/bg/subtle | neutral/100 #F4F1F0 | neutral/850 #211C1A | table header, hover row, segmented track |
| color/bg/muted | neutral/200 #E9E4E2 | neutral/800 #2A2421 | disabled button, skeleton |
| color/bg/input | #FFFFFF | #1A1513 | form controls |
| color/bg/input-disabled | #F4F1F0 | #211C1A | disabled control |
| color/bg/brand-subtle | brand/50 #FFF2EF | neutral/800 | tinted panels |
| color/bg/overlay | #110D0C | #110D0C | scrim (apply 48% opacity) |
| color/bg/inverse | brand/900 #41251E | neutral/50 | bulk bar, tooltips, avatar discs |
| color/sidebar/bg | brand/900 #41251E | neutral/950 | sidebar |
| color/sidebar/hover | brand/800 #7A2A17 | neutral/850 | item hover, active child |
| color/sidebar/active | brand/500 #FF522C | brand/500 | active top item pill |
| color/sidebar/text | brand/100 #FFE3DB | neutral/300 | items |
| color/sidebar/text-muted | brand/300 #FFA189 | neutral/500 | section labels, chevrons |
| color/sidebar/divider | brand/800 | neutral/800 | dividers |
| color/text/primary | brand/900 #41251E | neutral/50 | body text, headings |
| color/text/secondary | neutral/500 #717171 | neutral/400 | secondary text, labels |
| color/text/muted | neutral/400 #A9A09D | neutral/500 | placeholder, meta |
| color/text/on-brand | #FFFFFF | #FFFFFF | text on brand/danger fills |
| color/text/inverse | #FFFFFF | brand/900 | text on bg/inverse |
| color/text/brand | brand/500 | brand/400 | links, ghost buttons |
| color/border/default | neutral/200 | neutral/800 | card and row dividers |
| color/border/strong | neutral/300 | neutral/700 | input borders |
| color/border/focus | brand/500 | brand/400 | focus border |
| color/brand/primary · hover · pressed · subtle | 500 · 600 · 700 · 50 | 500 · 400 · 600 · 800 | primary actions |
| color/success/fg · bg | green/500 · green/50 | green/400 · green/950 | |
| color/warning/fg · bg | amber/500 · amber/50 | amber/400 · amber/950 | |
| color/danger/fg · bg · hover | red/500 · red/50 · red/500 | red/400 · red/950 · red/400 | |
| color/info/fg · bg | blue/500 · blue/50 | blue/400 · blue/950 | |

### 1.3 Spacing, radius, shadow
- **Spacing** (`space/*`, scopes GAP and WIDTH_HEIGHT): 2, 4, 8, 12, 16, 20, 24, 32, 40, 48.
- **Radius** (`radius/*`): sm **6**, md **10**, lg **16**, pill **999**. Usage:
  - sm: chips, menu items, small badges
  - md: buttons, inputs, toasts, icon chips
  - lg: cards, modals, dropzones
  - pill: badges, switches, avatars
- **Effect styles** (shadow color = ink `#41251E`):

| Style | Value | Use |
|---|---|---|
| Admin/Shadow/XS | 0 1 2 / 6% | cards, segmented active |
| Admin/Shadow/SM | 0 2 8 / 8% | hover lift |
| Admin/Shadow/MD | 0 8 24 / 10% | dropdowns, popovers, sticky bars |
| Admin/Shadow/LG | 0 16 40 / 16% | modals, drawers, toasts, bulk bar |
| Admin/Shadow/Focus ring | 0 0 0 3px `#FF522C` @ 24% | focus-visible on every interactive element |

### 1.4 Typography (text styles)
**Outfit** (`Admin/*`):

| Style | Size / line height | Weight |
|---|---|---|
| Display | 32/40 | SemiBold |
| Heading/H1 | 24/32 | SemiBold |
| Heading/H2 | 20/28 | SemiBold |
| Heading/H3 | 16/24 | SemiBold |
| KPI/Value | 28/36 | SemiBold |
| Body/Lg | 16/24 | Regular |
| Body/Md (default) | 14/20 | Regular / Medium |
| Body/Sm | 13/18 | Regular / Medium |
| Caption | 12/16 | Regular / Medium |
| Overline | 11/16 | SemiBold, uppercase, 6% tracking |
| Label | 14/20 | Medium |
| Button/Lg · Md · Sm | 16/24 · 14/20 · 13/16 | Medium |

**Arabic** (`Admin AR/*`): H1 24/36, H3 16/26, KPI 28/38, Body/Md 14/22, Caption 12/18. Arabic needs about 10–15% more line height.
- In code, use **Noto Sans Arabic** under `[dir=rtl]` exactly as in Figma (decision #89; the vendor panel keeps the client's IBM Plex Sans Arabic).

The minimum UI text size is 12px. The old admin used 10–11px; that is fixed here.

---

## 2. Layout & breakpoints
| Breakpoint | Width | Shell |
|---|---|---|
| xl | ≥ 1280 | Sidebar 264 expanded + topbar 64. Content padding 32. Max content 1112 at 1440. |
| lg | 1024–1279 | Sidebar collapsed to 76 (icon-only) by default, expandable as an overlay. Content padding 24. |
| md | 640–1023 | Sidebar becomes an off-canvas drawer (288, scrim). Topbar 56 with menu button. Tables scroll horizontally with the first column pinned. |
| sm | < 640 | Mobile topbar 56. Content padding 16. Tables become stacked cards. **Modal → Bottom Sheet**. Filters → "Filters (n)" button + bottom sheet. Toasts are top-center at full width minus 32. |

- **Desktop grid:** 12 columns, 16 gutter, in the 1112 content area. Common splits: 4 × KPI (266); 2/3 + 1/3 (728 + 368); main + side (≈752 + 344).
- **Vertical rhythm:** 24 between sections, 16 between cards, 20 card padding.
- **Sticky elements:** topbar; table header; the save bar on forms with unsaved changes; the brand header + footer of the sidebar. The sidebar nav scrolls on its own and auto-scrolls to the active item.

---

## 3. Components
All components are on the Admin Panel page, built with auto layout and bound to the `Admin` variables. Text uses the `Admin/*` styles.

| Component | Variants / props | Spec |
|---|---|---|
| **Button** | Variant: Primary / Secondary / Ghost / Danger × Size: Sm / Md / Lg × State: Default / Hover / Disabled / Loading. Props: Label, Show icon, Icon (swap) | **Heights:** 32 / 40 / 48.<br>**Horizontal padding:** 12 / 16 / 20.<br>**Gap:** 6 / 8 / 8. **Icon:** 16 / 18 / 20. **Radius:** 10.<br>**Colors:** Primary = brand → hover brand/600 → pressed brand/700. Secondary = surface with 1px border/strong; hover bg/subtle. Ghost = brand text; hover brand-subtle. Danger = red/500.<br>**Disabled:** bg/muted with text/muted. **Loading:** spinner + "Saving…", click blocked, width kept. |
| Icon Button | Variant: Ghost / Secondary / Primary × Size: Sm 32 / Md 40 | Square, radius 10. Must have an aria-label and a tooltip. |
| **Input** | State: Default / Focus / Filled / Error / Disabled. Props: Label, Value, Show helper, Required, Leading icon | **Control:** 44h, radius 10, padding 0×12, border 1px border/strong.<br>**Focus:** 1.5px brand + focus ring. **Error:** 1.5px danger + message.<br>**Label:** 14 Medium, red `*` when required. **Helper:** 12 secondary. Label–control gap 6. |
| **Select (Select2)** | Default / Open / Multi / Disabled | **Single:** chevron; the open dropdown has a search box, a check mark on the selected option and a brand-subtle highlight. Dropdown: radius 10, Shadow/MD, 8px padding, options 36h.<br>**Multi:** removable brand-subtle tags (radius 6). |
| **Phone Input** | Default / Open / Error | Flag 22×15 + dial code + chevron, a divider, then the number. Opens a searchable country list (flag, name, code). Stored as E.164. |
| **Date Picker** | Single / Range / Range Open | Popover: presets column (Today, 7d, 30d, This month, Custom) + month grid with 28px cells. Range ends use a brand fill; days in range use brand-subtle. |
| Textarea | Default / Focus / Error | Min height 112, padding 10×12, vertical resize, character counter in the helper slot. |
| Checkbox | Unchecked / Checked / Indeterminate / Disabled | 18px box, radius 5, 1.5px border. Checked = brand fill with a white glyph. |
| Radio | Unchecked / Checked / Disabled | 18px. Checked = 5px brand ring. |
| Switch | On / Off / Disabled | Track 36×20 (pill), knob 16. On = brand. Updates optimistically and shows a toast. |
| File Upload | Default / Drag over / Uploading / Uploaded / Error | **Dropzone:** dashed 1.5px (6/4), radius 16, padding 24, 40px icon disc. Drag over = brand border + brand-subtle fill.<br>**File row:** 4px progress bar, then a success or error row with retry. |
| Image Upload | Empty / With images | 96px tiles, radius 10. The first tile is "Main" (inverse badge). Tiles can be reordered by dragging. On hover: delete and crop. |
| **Locale Tabs** | EN active / AR active / AR error | Segmented control on bg/subtle. A tab with an error shows a red dot and opens automatically on submit. The AR pane uses `dir=rtl`. |
| **Badge / status pill** | Tone: Neutral / Brand / Success / Warning / Danger / Info × Style: Soft / Dot / Count | 20h, pill, 12 Medium, padding 2×10.<br>**Status mapping:** draft/inactive = Neutral · new/featured = Brand · paid/delivered/active = Success · pending/processing = Warning · cancelled/failed/refunded = Danger · shipped/in transit = Info. |
| Tab | Type: Underline / Segmented × State: Default / Hover / Active. Props: Label, Show count | **Underline:** 12px vertical padding, 2px brand indicator, 24 gap between tabs. **Segmented:** 6×12 inside a 4px track. The count is a pill. The active tab is kept in the URL (`?tab=`). |
| **KPI Card** | Style: Default / Highlight × Trend: Up / Down / Neutral. Props: Label, Value, Delta, Icon | Width 296 (FILL in grids), padding 20, radius 16, Shadow/XS. Contents: 40px icon chip (brand-subtle), 28/36 value, trend pill, comparison text, sparkline.<br>Highlight = gradient `#FF522C → #41251E`; use it once per dashboard. |
| **Chart Card** | Type: Line / Bar / Donut. Prop: Title | **Header:** title + subtitle + 7D/30D/12M segmented + menu.<br>**Series:** brand/500 first, brand/900 second, status colors for status breakdowns. Gridlines use border/default. Axis labels are 12px muted. Chart.js in code. |
| **Table Cell** | Header / Header checkbox / Checkbox / Text / Text + sub / Product / Badge / Number / Actions / Skeleton | **Row** 56, **header** 44 on bg/subtle, cell padding 0×16, bottom divider. Numbers are right-aligned. The last action is the kebab menu. |
| **Data Table** | State: Default / Bulk selected / Loading / Empty | Card (radius 16) with: Filters Bar, header row, rows, Pagination.<br>Hover row = bg/subtle. Selected row = brand-subtle and the header checkbox shows indeterminate.<br>Loading = skeleton rows (header stays visible). Empty = Empty State "No results". |
| Filters Bar | — | Search 280 (debounced 300ms, camera icon = image search) + quick selects (40h) + "More filters" (opens the Drawer) + list/grid toggle + column chooser. Active filter chips sit below with "Clear all". Everything is synced to the URL. |
| Pagination | — | "Showing x–y of N" + per-page select + 32px page buttons (active = brand). On mobile: Prev / "Page x of N" / Next. |
| Row Actions Menu | — | 200 wide, items 36h, radius 6. Destructive items come last, in danger color, and open the Confirm Dialog. |
| Bulk Action Bar | — | Floats bottom-center, 24 from the bottom edge. Inverse surface, radius 10, Shadow/LG. Shows the selected count + actions + Clear. |
| **Page Header** | Default / With tabs / Detail | Breadcrumb (13px, chevrons) → H1 + status badge + subtitle → actions on the right (primary last). Optional underline tab row. |
| **Modal** | — | Widths sm 400 / md 560 / lg 720 / xl 960, radius 16, Shadow/LG.<br>**Structure:** header 20×24 with close; body padding 24; footer on bg/subtle with actions on the right.<br>**Scrim:** overlay at 48%. Esc or scrim click closes it; if the form is dirty, a confirm appears first. Focus is trapped. |
| **Bottom Sheet** | — | The < 640px version of the Modal: full width, top radius 16, 40×4 grabber, max height 90vh.<br>Full-width stacked actions with the primary first. Safe-area bottom padding 28. Swipe down closes it. |
| Drawer | — | 420 wide, opens from the inline-end side (right in LTR, left in RTL). Header / scrolling body / sticky footer. Full width under 640. |
| Confirm Dialog | — | 400 wide. 48px tone disc, H2 title, message, then Cancel + Danger buttons at full width. Focus starts on Cancel. For irreversible actions the user must type the item's name. |
| Toast | Type: Success / Error / Warning / Info | 380 wide, 4px leading accent stripe (mirrored in RTL), Shadow/LG.<br>**Auto-dismiss:** Success/Info 5s, Warning 8s, Error stays until closed.<br>**Stacking:** max 3, 12 gap. Top-right on desktop, top-center on mobile. |
| Empty State | No data / No results / Error | 88px disc (Lottie in code), H3, description, one action. |
| Notification Dropdown | — | 380 wide. Header with unread count + "Mark all read". Rows show a 36px tone chip, title, meta and time; unread rows have a brand-subtle tint and a dot.<br>Max 6 rows, then the list scrolls. Footer link "View all". New items arrive over Reverb and the bell badge pulses once. |
| User Menu | — | 260 wide: profile header, then My profile, Security & 2FA, Language, Dark mode, then Sign out (danger). |
| Logo | On dark / On light | Placeholder lockup: mark, NOGRGR wordmark and ADMIN tag. Replace the mark with the brand SVG from navbar `12:42345`. |
| **Sidebar Item** | Level: Top / Child / Collapsed × State: Default / Hover / Active. Props: Label, Icon, Show count, Show chevron, Show dot | **Top:** 40h, radius 10, icon 20, 14px label, count pill, chevron for groups. **Child:** 36h, 44 indent, 6px bullet, 13px label. **Collapsed:** 44×40, icon only, unread dot.<br>**Keyboard:** ↑/↓ move between items; →/← open and close a group. |
| Sidebar Section | Prop: Label | Overline in sidebar/text-muted, 32h. In collapsed mode it becomes a 1px divider. |
| **Sidebar** | Mode: Expanded 264 / Collapsed 76 | **Structure:** brand header 64, scrolling nav, user footer (avatar, name, role, collapse chevron).<br>**Groups:** named `Children / <Item>`; open one by making it visible. One group is open at a time; the open group is remembered in localStorage.<br>**Collapsed mode:** hovering an item opens a flyout submenu. |
| **Topbar** | Device: Desktop 64 / Mobile 56 | **Desktop:** collapse toggle, global search 420 (Ctrl/Cmd K command palette), language switch (EN/AR flips `dir`), light/dark toggle, bell (count badge), avatar → User Menu.<br>**Mobile:** menu, logo, search, bell, avatar. |
| Icons | 73 components `Icon/*` | 24 grid, 1.75 stroke, round caps (Lucide-style). Used at 16/20/24. Recolor by overriding the vector stroke. In buttons, cells and items they are passed through instance-swap props. |

**Interaction states everywhere:**
- Hover: bg/subtle, or brand/600 on brand fills.
- Pressed: brand/700.
- Focus-visible: Shadow/Focus ring. It must never be removed.
- Disabled: muted colors and `cursor: not-allowed`. Opacity is used only on composite fields (70%).

---

## 4. Sidebar information architecture
Every admin module from `01-modules.md` §A is included, plus the §D placements. The Figma IA map is frame `57:17014`.

| Section | Top-level item (group children) | Source |
|---|---|---|
| — | Dashboard | §A7 |
| Catalog | Products (All products · Add product · Import (Neozena) · Sort products) · Categories · Attributes · Labels & badges (Product labels · Product badges) | §A1, §D |
| Requests | Requests center [count] (Product · Edit · Manufacturing · Brand · Tenant change requests) | §A1, §D |
| Sales | Orders [count] · Shipping queue · Returns & refunds [count] (Return requests · Refunds) · Promotions (Coupons · Flash sales) | §A2 |
| Logistics | Shipping (Shipping companies · Rules & zones · Fixed shipping costs · Stop periods · Delivery popup days) · Branches | §A3 |
| Vendors | Vendors [count] (All vendors · Pending registrations · Compliance / KYC · Domains & DNS · Shoppers) · Plans · Subscriptions | §A4, §D |
| Finance & growth | Finance (Ledger · Settlements · Payouts · Transactions · Invoices · Payment logs) · Affiliates (Accounts · Conversions · Payouts · Promo codes · Program settings) · Reports | §A4, §A7, §D |
| Content | Storefront (Themes · Theme review queue · Banners · Return policy) · Website (Blog posts · Blog categories · Static pages · FAQs · Newsletter subscribers) | §A5, §D |
| System | Localization (Languages · Translations · Currencies · Countries) · Settings (General · Integrations · Email templates) · Support tickets [count] · Notifications · Admins & roles · Audit log · System tools (Maintenance windows · Cache tools · Developer docs) | §A6, §A6b, §A6c, §D |

**Where items that have no sidebar entry live:**
- **Vendor profile tabs:** impersonate (audited), plan limits override, mobile app.
- **Integrations › Payment gateways › Limits** tab.
- **Order detail:** receipt and label.

**Navigation rules:**
- Items are hidden when the admin lacks the matching `*.view` permission.
- Count badges are live over Reverb.
- The breadcrumb follows Section › Group › Page.

---

## 5. Screens delivered (node ids in §8)
1. **Dashboard:**
   - Desktop: 4 KPIs, revenue line chart, orders-by-status donut, recent orders, needs-attention list, top vendors, platform health.
   - Mobile 375.
   - Arabic RTL.
   - Dark mode (the same frame with the `Admin` collection set to Dark).
2. **Products list:**
   - Desktop: page header, status segmented tabs with counts, Data Table.
   - Mobile 375: card list, search + Filters button, status chips, pager.
   - Mobile with the Filters **bottom sheet**.
3. **Product edit**, two frames:
   - Variants tab: attributes builder, variants table with per-row inputs and active switch, Status / Organization / Countries / Tenant assignment side cards, sticky save bar.
   - Translations tab: EN source next to AR (RTL), "Translate with AI", missing-field error, language completeness, note on vendor overrides.
4. **Orders list:** 7 status tabs with counts, Filters Bar, 8 rows (payment and status pills), pagination.
5. **Order detail:**
   - Status stepper.
   - Items.
   - Payment summary, including the platform coupon and the vendor / platform / gateway revenue split.
   - Shipping & tracking (company, AWB, estimate that skips stop periods, timeline).
   - Side cards: customer, address, vendor, payment (refund), internal notes.
6. **Settings › Integrations:**
   - Payment gateways tab: gateway list (COD, Stripe, PayPal, Paymob, Tap, MyFatoorah, Moyasar, Tabby, Tamara) and, for Stripe, **two configs side by side**:
     - **Vendor payments**: Live, verified.
     - **Tenant registration**: Test, not verified.
     - Each config has Test/Live mode, an enable switch, masked keys, webhook secret, currencies and Test connection.
     - Sub-tabs: Credentials / Limits / Webhooks / Logs.
   - A second frame holds the bodies of the **Mail**, **Google login**, **Apple login** and **OpenAI** tabs. OpenAI includes budget and usage.
7. **Delivery popup days:**
   - Valid state: days 1–10, each with a % input, a proportional bar and a "Shown / Hidden on storefront" badge. The total row reads 100% in green, "Ready to save". A storefront preview card is included.
   - Invalid state: Day 3 is changed to 10%, so the total reads 90% in red, "Add 10% to save". Save is disabled and a warning toast appears.
8. **Login:** brand gradient panel + sign-in card (email, password, remember device, forgot, Sign in, Google, 2FA / IP allow-list note), language switch.

---

## 6. Motion
| Interaction | Duration / easing | Notes |
|---|---|---|
| Hover / press colors | 120ms ease-out | background and border only |
| Focus ring | instant in, 120ms out | never animate it in with a delay |
| Dropdown / popover / menu | 160ms `cubic-bezier(.2,.8,.2,1)`; opacity 0→1 + translateY(-4→0) | the exit is 100ms |
| Modal | 200ms; scrim fades in, card scales .98→1 + fades | exit 150ms |
| Bottom sheet | 280ms `cubic-bezier(.32,.72,0,1)` translateY(100%→0) | drag to dismiss; closes when dragged more than 30% down or flicked faster than 0.5 px/ms |
| Drawer | 240ms translateX from the inline-end side | mirrored in RTL |
| Sidebar collapse | 200ms width 264↔76; labels fade out at 80ms | group open/close: 180ms height + chevron rotation |
| Toast | in 220ms slide + fade from the top-right (top on mobile); out 160ms | the bell badge pulses once (scale 1→1.15→1, 400ms) when a new notification arrives |
| Skeleton | shimmer 1.2s linear infinite | replaced by content without a layout shift |
| Charts | 400ms ease-out on first draw only | no animation on filter changes over 1,000 points |
| Number change (KPI) | 300ms count-up | only on first load |

With `prefers-reduced-motion`, everything becomes a ≤ 80ms opacity fade, and shimmer, count-up and chart draw animations are turned off.

---

## 7. RTL, dark mode, accessibility
**RTL:**
- Set `dir="rtl"` on `<html>` when the language is Arabic. Use only logical CSS properties (`margin-inline-start`, `inset-inline-end`, …).
- The sidebar moves to the right, horizontal stacks reverse, and text aligns right.
- Directional icons (chevrons, arrows, the toast stripe) are mirrored.
- Numbers, SKUs, emails and order IDs stay LTR (`<bdi>` / `dir="ltr"`).
- Charts keep a left-to-right time axis.
- Reference frame: `56:17016`.

**Dark mode:**
- Switch the `Admin` collection mode. In code, use `[data-theme="dark"]` on `<html>`.
- The choice is remembered per admin. The default follows `prefers-color-scheme`.
- Reference frame: `57:17198`.

**Accessibility:**
- Contrast is at least 4.5:1 for text: ink `#41251E` on white ≈ 13:1, gray `#717171` on white ≈ 4.8:1. Brand `#FF522C` on white ≈ 3.2:1, so it is used only for large or bold text and icons. Links also get an underline on hover.
- Hit targets are at least 32px on desktop and 40px on touch.
- Every icon-only control has an aria-label.
- Tables use proper `<th scope>`, and sort state is exposed with `aria-sort`.

---

## 8. Node index (page "Admin Panel" `23:10147`)

### Foundations & documentation frames
| Node | Name |
|---|---|
| 40:11992 | Admin Panel / Foundations / Tokens (primitives, semantic Light + Dark, type, spacing, radius, shadow) |
| 24:10147 | Admin Panel / Foundations / Icons (73 icon components) |
| 25:10147 | Admin Panel / Components / Buttons |
| 27:10165 | Admin Panel / Components / Form fields |
| 28:10190 | Admin Panel / Components / Selection & upload |
| 29:10239 | Admin Panel / Components / Data display |
| 31:10272 | Admin Panel / Components / Feedback & page |
| 32:10347 | Admin Panel / Components / Overlays |
| 33:10474 | Admin Panel / Components / Data table |
| 35:11319 | Admin Panel / Components / Navigation |
| 57:17014 | Admin Panel / IA / Sidebar map |

### Component sets and components
| Node | Component | Variants |
|---|---|---|
| 25:10355 | Button | 48 |
| 25:10386 | Icon Button | 6 |
| 27:10219 | Input | 5 |
| 27:10284 | Select | 4 |
| 27:10346 | Phone Input | 3 |
| 27:10490 | Date Picker | 3 |
| 27:10512 | Textarea | 3 |
| 28:10210 | Checkbox | 4 |
| 28:10220 | Radio | 3 |
| 28:10233 | Switch | 3 |
| 28:10325 | File Upload | 5 |
| 28:10357 | Image Upload | 2 |
| 28:10380 | Locale Tabs | 3 |
| 29:10291 | Badge | 18 |
| 29:10316 | Tab | 6 |
| 29:10395 | KPI Card | 4 |
| 29:10505 | Chart Card | 3 |
| 31:10312 | Toast | 4 |
| 31:10356 | Empty State | 3 |
| 31:10467 | Page Header | 3 |
| 33:10532 | Table Cell | 10 |
| 34:11868 | Data Table | 4 |
| 35:11335 | Logo | 2 |
| 35:11405 | Sidebar Item | 9 |
| 35:11474 | Topbar | 2 |
| 37:12436 | Sidebar | 2 |
| 32:10353 | Modal | — |
| 32:10414 | Bottom Sheet | — |
| 32:10454 | Confirm Dialog | — |
| 32:10474 | Drawer | — |
| 32:10542 | Notification Dropdown | — |
| 32:10608 | User Menu | — |
| 33:10533 | Pagination | — |
| 33:10558 | Row Actions Menu | — |
| 33:10592 | Bulk Action Bar | — |
| 33:10628 | Filters Bar | — |
| 35:11406 | Sidebar Section | — |
| 37:12437 | Sidebar demo / Products open (instance) | — |

### Screens
| Node | Screen | Size |
|---|---|---|
| 41:12632 | Dashboard — Desktop | 1440×1696 |
| 43:13511 | Dashboard — Mobile | 375×1337 |
| 56:17016 | Dashboard — RTL Arabic — Desktop | 1440×1696 |
| 57:17198 | Dashboard — Dark mode — Desktop | 1440×1696 |
| 44:13636 | Products list — Desktop | 1440×1024 |
| 45:14601 | Products list — Mobile | 375×940 |
| 45:14748 | Products list — Mobile / Filters bottom sheet | 375×812 |
| 46:14760 | Product edit — Variants tab — Desktop | 1440×1308 |
| 47:15543 | Product edit — Translations tab — Desktop | 1440×1024 |
| 48:16236 | Orders list — Desktop | 1440×1024 |
| 49:17280 | Order detail — Desktop | 1440×1396 |
| 51:17971 | Settings › Integrations — Payment gateways — Desktop | 1440×1134 |
| 53:18783 | Settings › Integrations — Mail / Google / Apple / OpenAI tab bodies | 1176×1232 |
| 54:19015 | Delivery popup days — valid (100%) | 1440×1024 |
| 54:19898 | Delivery popup days — invalid total (live validation) | 1440×1024 |
| 55:20387 | Login — Desktop | 1440×1024 |

---

## 9. Known gaps / follow-ups
- **IBM Plex Sans Arabic** is not available in this Figma account. Arabic text styles use Noto Sans Arabic, and the code uses the same family (decision #89).
- The **RTL dashboard** is made of detached layers (a mirrored clone), not live instances. Do not edit components there; edit the LTR source and re-mirror.
- The **Logo mark** is a placeholder; swap in the official SVG.
- Gateway logos are colored monograms. Real logos must come from the brand kits.
- The **Integrations screen** shows the sidebar scrolled to the active "Settings › Integrations" item: the sidebar is wrapped in a clip frame with a pinned brand header.
- Not yet designed (built later from this kit):
  - Mobile versions of the other admin screens.
  - Vendor profile.
  - Reports tabs.
  - Requests center chat.
  - Theme review queue.
  - Translations editor.

---

# Batch 2 — Remaining admin & vendor panel screens

Every remaining admin and vendor page has a design. Frames are named with their `05-page-coverage.md` row IDs, so a search for `ADM-0xx` or `VEN-0xx` in Figma finds them. Each module group sits in a named **Section**. Modals are collected on per-module "Modals" sheets next to the screens they belong to. All amounts are in **USD**.

## Tokens added in batch 2
- **Vendor mode:** a new mode **"Vendor"** (mode id `62:0`) on the `Admin` collection.
  - It aliases new primitives `vbrand/*` (#FD6F04 scale: 50 #FFF1E6, 200 #FED2B1, 500 #FD6F04, 600 #E66504, 900 #8B3D02), `vneutral/*` (#FFFFFF…#0B0B0B), `vgreen/vred/vamber/vblue` 50/500.
  - Sidebar: white background, #363636 text, #FD6F04 active pill.
  - `radius/md` = 12 in Vendor mode.
  - Vendor frames set this mode explicitly. The same components render in vendor colours without separate copies.
- **Button radius:** `radius/button` was created (Light 10 / Dark 10 / Vendor 0) but is **not bound** to the existing Button components, because batch-1 components were kept unchanged. In code, vendor buttons should use `--ng-radius-button: 0` (square, as in the Tenant Panel).
- **Vendor Sidebar** component `62:21332` (page `Vendor Panel (new screens)` `60:10147`).
  - Built from `Sidebar Item` instances: Dashboard, Today's chances, Products▸, Orders▸, Customers▸, Your Store▸, Ads & Marketing▸, Analytics, Requests▸, Partner Program, Finance▸, Settings▸ (18 children), Support, Help center, Notifications.
  - Store card in the footer.
- **Long sidebars:** screens whose active item sits low in the nav show the sidebar scrolled to that item, with the brand header pinned (frame named `Sidebar (scrolled to active item)`).

## Admin — page "Admin Panel" (`23:10147`)
| Section (node) | Frames (node · name) |
|---|---|
| Catalog `65:30034` | 65:21308 ADM-012 Product sort · 65:22120 ADM-013 Import (Neozena) · 65:22996 ADM-014 Categories tree · 65:23866 ADM-014a Category form · 65:24654 ADM-014b/c Category sort & products order · 65:25447 ADM-015 Attributes · 65:26338 ADM-015a Attribute form (options) · 65:27123 ADM-016 Product labels · 65:27887 ADM-017 Badges hub (017a–e, 017g) · 65:28688 ADM-011b/d/e/f/g/h Product edit other tabs · 65:29682 Modals ADM-010a image search, 010b price list, 016a label form, 017f assign products |
| Requests center `68:35709` | 68:28760 ADM-018/018c hub + product requests · 68:29747 ADM-018a edit requests · 68:30673 ADM-018b edit-request diff · 68:31430 ADM-018d product request chat · 68:32218 ADM-018e manufacturing list · 68:33125 ADM-018f manufacturing detail + payment requests · 68:33923 ADM-018g/h brand request · 68:34678 ADM-018i tenant change requests · 68:35573 Modals (reject, payment request, status) |
| Sales & after-sales `70:41837` | 70:34839 ADM-022 Shipping queue · 70:35864 ADM-022a Shipment detail (tracking, attachments, label) · 70:36668 ADM-023 Returns · 70:37613 ADM-023a Return detail (state machine) · 70:38484 ADM-024 Refunds · 70:39532 ADM-025 Coupons · 70:40462 ADM-026 Flash sales · 70:41386 Modals ADM-021a cancel, 021b refund, 021c create return, 021d reassign company, 023a inspect, 024 retry/complete/reject, 025a coupon, 026a flash sale |
| Logistics `71:48886` | 71:40880 ADM-030 Branches · 71:41812 ADM-030a Branch form + stock · 71:42662 ADM-031 Fixed costs + calculator · 71:43578 ADM-032 Rules · 71:44486 ADM-032a Rule form (zones, weight bands, overlap error) · 71:45372 ADM-033 Companies · 71:46296 ADM-033a/b/d Company details, day rules, default company, estimator · 71:47208 ADM-033c Stop-periods calendar · 71:48071 ADM-034 Shipping settings · 71:48842 Modal ADM-031a |
| Vendors (1) `72:52561` | 72:47934 ADM-040 Vendors list · 72:48932 ADM-040a Create vendor · 72:49758 ADM-041/041a Profile overview · 72:50656 ADM-041b Plan & subscription · 72:51480 ADM-041c–h Domains, profit, stats, compliance, mobile app, limits · 72:52459 Modals ADM-041i impersonate, assign plan |
| Vendors (2), Plans & Subscriptions `73:59897` | 73:51922 ADM-042 Pending registrations · 73:52854 ADM-043 Shoppers · 73:53809 ADM-044 Compliance · 73:54733 ADM-044a Compliance detail · 73:55481 ADM-045/045a Domain requests · 73:56421 ADM-045b DNS templates · 73:57264 ADM-046 Plans · 73:58030 ADM-046a Plan form · 73:58869 ADM-047 Subscriptions |
| Finance & Affiliates `74:69228` | 74:59026 ADM-050 Ledger · 74:60018 ADM-050a Tenant ledger · 74:60809 ADM-051 Settlements · 74:61743 ADM-052 Payouts · 74:62688 ADM-052a Create payout · 74:63449 ADM-053 Transactions · 74:64375 ADM-054 Invoices · 74:65309 ADM-054a Invoice detail · 74:66052 ADM-055/055a Payment logs + detail · 74:66931 ADM-060 Affiliate accounts · 74:67899 ADM-061/062/063 Conversions, payout requests, promo codes · 74:68877 Modals ADM-050a, 052b, 053, 060a–c, 061, 062, 063 |
| Reports `75:70967` | 75:68061 ADM-070/070a Sales · 75:69019 ADM-070b–f Orders, profit, refunds & returns, shipping, promotions · 75:69998 ADM-070g–l Subscriptions, affiliates, vendors, products, customers, AI usage |
| Content (website) `76:76766` | 76:70524 ADM-080 Blog categories · 76:71416 ADM-081 Blog posts · 76:72347 ADM-081a Post editor · 76:73162 ADM-082 Static pages · 76:74135 ADM-082a Page editor (target central/storefront/both) · 76:74912 ADM-083 FAQs · 76:75699 ADM-084 Newsletter · 76:76659 Modals ADM-080a, 083a |
| Storefront `78:82914` | 78:76059 ADM-085 Banners · 78:76835 ADM-086 Themes (13) · 78:77708 ADM-086a Theme edit · 78:78508 ADM-087 Home variants & sections · 78:79365 ADM-088/088a Template parts · 78:80277 ADM-089 Theme review queue · 78:81175 ADM-089a Review detail (validation report, versions, approve/reject) · 78:81991 ADM-090 Return policy · 78:82810 Modal ADM-085a |
| Localization & Settings `79:91191` | 79:81928 ADM-091 Languages · 79:82911 ADM-092/093 Translations editor + key locks · 79:83875 ADM-094 Purchases · 79:84722 ADM-095 Currencies (rate sync) · 79:85682 ADM-096 Countries · 79:86634 ADM-100c Gateway limits · 79:87472 ADM-100h–k Reverb/Pusher, FCM, Cloudflare/DNS, tracking defaults · 79:88365 ADM-101 General settings · 79:89188 ADM-102 Email templates · 79:90124 ADM-102a Template editor · 79:90884 Modals ADM-091a, 091b (AI catalog progress), 092a, 095a, OpenAI test result |
| System, Support & Account `80:98574` | 80:90095 ADM-110 Admins · 80:91028 ADM-111/111a Roles + permission matrix · 80:91969 ADM-112 Audit log · 80:92875 ADM-113 Maintenance · 80:93684 ADM-114 Cache tools · 80:94467 ADM-115/115a Developer docs · 80:95200 ADM-116 Tickets · 80:96112 ADM-116a Ticket chat · 80:96878 ADM-007/007a Notifications + preferences · 80:97736 ADM-005 My profile · 80:98485 Modals ADM-110a, 112a |
| Auth, errors, search & mobile `81:100039` | 81:97508 ADM-002 Forgot password · 81:97545 ADM-003 Reset password · 81:97588 ADM-120 403 · 81:98238 ADM-121 404 · 81:98888 ADM-122 419 · 81:98906 ADM-123 500 · 81:98931 ADM-124 503 · 81:98948 Global search results · 81:99723 Mobile pattern List 375 · 81:99821 Mobile pattern Form 375 · 81:99928 Mobile pattern Detail 375 |

**Admin mobile rules (all modules):**
- Tables become cards (title, meta line, status pill, kebab menu).
- Filters open in a bottom sheet.
- Forms are a single column, and side cards move below the main fields.
- The save button is sticky and full width.
- Detail pages use header stats plus segmented sections.
- Modals become bottom sheets.

## Vendor — page "Vendor Panel (new screens)" (`60:10147`)
| Section (node) | Frames (node · name) |
|---|---|
| Vendor Sidebar `62:21332` | component |
| Auth, onboarding, states & errors `82:102251` | 82:99816 VEN-001 Login · 82:99866 VEN-002/003 Forgot/reset · 82:99909 VEN-004 Verify email · 82:99932 / 82:99996 / 82:100084 / 82:100161 Onboarding wizard (Countries → Languages → Theme → Launch; also WEB-021a–d) · 82:100227 VEN-007a/b + 008 Tour, setup checklist, progress widget · 82:101033 VEN-005 Impersonation banner · 82:101634 VEN-095 Suspended/expired · 82:102128 VEN-096 403 · 82:102150 VEN-097 404 · 82:102172 VEN-098 419 · 82:102194 VEN-099 500 · 82:102216 Modal VEN-094 plan limit |
| Products & AI tools `83:104522` | 83:100772 VEN-012/012a Pricing per country/variant · 83:101628 VEN-012b–e Content & AI translate, stock & weight, categories & badges, central snapshot · 83:102290 VEN-013 Edit requests · 83:102998 VEN-014/017c Product & category sort · 83:103600 VEN-018/018a/018b Badges · 83:104181 Modals VEN-010a price list, 010b AI price finder, 010d share, 011a new product preview, AI translate preview, AI social posts, 013a submit edit request |
| Orders & customers `84:107489` | 84:102416 VEN-021 Order detail + settlement · 84:103244 VEN-021d Pay platform · 84:103852 VEN-022 Returns · 84:104582 VEN-022a Return detail · 84:105181 VEN-023/024 Refunds + return analytics · 84:105854 VEN-025b Customer detail · 84:106555 VEN-026 Newsletter · 84:107236 Modals VEN-021a, 021b, 021c, 025a, 025c |
| Your Store `85:110253` | 85:104756 VEN-030/031 Themes per country + home variant · 85:105527 VEN-032 Page builder · 85:106165 VEN-033/033a Pages · 85:106903 VEN-034/034a Logo + AI logo · 85:107559 VEN-034b–f Colors (36 swatches) & fonts, promo, header links, footer, social · 85:108271 VEN-035 Banners · 85:108862 VEN-036/036a/036b Custom Twig themes · 85:109494 VEN-037/037a Mobile app · 85:110108 Modals VEN-030a, 030b, 035a |
| Marketing, analytics, requests & partner `87:112261` | 87:107268 VEN-040 Coupons · 87:108154 VEN-041 Flash sales · 87:108835 VEN-045b/045d Analytics tabs · 87:109541 VEN-050c Product request chat · 87:110114 VEN-050f/050i Manufacturing & brand chat + pay · 87:110730 VEN-050j Payment result · 87:111264 VEN-055 Partner program · 87:111915 Modals VEN-040a, 041a, 041b, 050h, 055a |
| Finance, support & notifications `88:113552` | 88:109601 VEN-060/060c/060d Subscription & balance · 88:110393 VEN-061/061a Invoices · 88:111108 VEN-062/063/064 Money flow · 88:111731 VEN-091/091b Tickets + thread · 88:112298 VEN-092/092a Help center · 88:112846 Notifications inbox · 88:113398 Modals VEN-060a, 060b, 091a |
| Settings (1) `90:117702` | 90:111315 VEN-070 Account · 90:112042 VEN-071 General · 90:112623 VEN-072 Currencies · 90:113194 VEN-073/073a/073b Languages · 90:113836 VEN-074/074a AI translation · 90:114518 VEN-075 Store translations · 90:115176 VEN-076/076a Staff · 90:115822 VEN-077/077a Roles · 90:116465 VEN-078/078a/078b Payment gateways + readiness · 90:117144 VEN-079 Return policy |
| Settings (2) `91:120035` | 91:114835 VEN-080/080a Email templates · 91:115511 VEN-081 SMTP · 91:116087 VEN-082/082a Domains + DNS check · 91:116736 VEN-083 Tracking pixels · 91:117308 VEN-084 Compliance center · 91:117954 VEN-085 API keys · 91:118575 VEN-086 Audit log · 91:119215 VEN-087 Notification prefs · 91:119876 Modals VEN-071a, 071b, add domain |
| UI kit `92:131175` | 92:130313 VEN-093 UI kit (vendor mode of the shared library) |

## Vendor — Tenant Panel first (decision #101, audit `vendor-figma-audit.md`)
- Screens that exist on client page `12:10147` are built from 12:10147 only. Frames on `60:10147` are for missing screens and must use the 12:10147 look: flat 236 px sidebar (`12:10990` / `12:10996` items), 74 px top bar, bg #F3F3F3, borderless r12 cards, Bold 32 titles, square `Component 1` buttons (`12:40267`), `State` pills (`12:40366`), `KPI Card` (`12:40706`), filled r8 inputs, and the black mobile header with `Nav bar mobile` (`12:11131`).
- The batch-2 shell (`62:21332` sidebar, Ctrl-K top bar, r10 buttons, bordered cards) is superseded.
- New aligned frames:
  - VEN-074 AI translation: `180:28706` (en), `180:29374` (ar RTL), `182:29054` (mobile)
  - section `184:29202`: VEN-021 `184:29203`, VEN-012 `184:29780`, VEN-022 `184:30357`, VEN-076 `184:30994`

## Known gaps (batch 2)
- Product thumbnails, banners and theme previews are gradient placeholders. ComfyUI image generation was not used in this batch.
- Arabic sample text inside Outfit-styled components renders with Figma's fallback font. Code uses IBM Plex Sans Arabic in the vendor panel (decision #89).
- Wide tables on narrow cards were cut down to the key columns. Responsive behaviour is defined by the three mobile pattern frames.

## Coverage cross-check vs `05-page-coverage.md`
Of the **317** admin and vendor rows marked "design needed", every one has a frame: either in batch 1 (ADM-001, 004, 006, 010, 011, 011a, 011c, 020, 021, 035, 100, 100a, 100b, 100d–g) or in batch 2. Some rows are covered inside a combined frame or a modal sheet, under shortened IDs. Their full IDs are:

| Row(s) | Frame (node) |
|---|---|
| ADM-010b, ADM-016a, ADM-017f | Modals / Catalog `65:29682` |
| ADM-017a, ADM-017b, ADM-017c, ADM-017d, ADM-017e, ADM-017g | Badges hub `65:27887` |
| ADM-021b, ADM-021c, ADM-021d, ADM-025a, ADM-026a | Modals / Sales `70:41386` |
| ADM-052b, ADM-060a, ADM-060b, ADM-060c | Modals / Finance & affiliates `74:68877` |
| ADM-062, ADM-063 | Affiliate activity `74:67899` (+ modals `74:68877`) |
| ADM-083a | Modals / Content `76:76659` |
| ADM-091b, ADM-092a, ADM-095a | Modals / Localization & settings `79:90884` |
| ADM-093 | Translations editor + key locks `79:82911` |
| ADM-112a | Modals / System `80:98485` |
| ADM-114a, ADM-114b | Cache tools `80:93684` |
| VEN-003 | Forgot & reset `82:99866` |
| VEN-008 | Tour, setup checklist & progress widget `82:100227` |
| VEN-010b, VEN-010d, VEN-011a, VEN-013a | Modals / Products & AI `83:104181` |
| VEN-017c | Product & category sort `83:102998` |
| VEN-021b, VEN-021c, VEN-025a, VEN-025c | Modals / Orders & customers `84:107236` |
| VEN-024 | Refunds + return analytics `84:105181` |
| VEN-030b, VEN-035a | Modals / Your Store `85:110108` |
| VEN-031 | Themes per country + home variant `85:104756` |
| VEN-041a, VEN-041b, VEN-050h, VEN-055a | Modals / Marketing & requests `87:111915` |
| VEN-060b, VEN-091a | Modals / Finance & support `88:113398` |
| VEN-063, VEN-064 | Money flow `88:111108` |
| VEN-071b | Modals / Settings `91:119876` |

**Uncovered admin and vendor rows: none.** Affiliate (AFF), owner portal (OWN), central website (WEB, except the onboarding wizard WEB-021a–d), storefront (SF) and mobile-app rows were out of scope for this batch.

---

# Storefront additions (page "Storefront additions" `97:10147`)

The canonical address form, the checkout address step and the customer account pages, per theme family (Ecommet / Elora / Souqify), at desktop 1440 and mobile 375. All frames are new. The client pages were only read, and their navbar / footer / mobile-nav components were instanced:
- Ecommet header `1:411`, footer `1:573`, mobile nav `1:2879`
- Elora navbar `1:20867`
- Souqify navbar `1:81294`, footer `1:81429`, mobile nav `1:80723`

Elora's footer and mobile bottom nav are rebuilt in Elora style, because they are not components.

**Tokens:** new collection **"Storefront"** (`VariableCollectionId:97:10148`) with modes **Ecommet `97:0` · Elora `97:1` · Souqify `97:2`**. Code syntax `var(--sf-*)`.

| Token | Ecommet | Elora | Souqify |
|---|---|---|---|
| cta | #242424 | #FF4D00 | #0159ED |
| accent | #DE1709 | #FF4D00 | #0159ED |
| accent-subtle | #FFECEA | #FFF1EB | #E8F0FE |
| ink | #242424 | #121212 | #001537 |
| muted | #717171 | #8F8F8F | #5B6B85 |
| border | #E8E8E8 | #D4D4D4 | #D9DEE8 |
| bg | #FFFFFF | #FDFDFD | #F1F1F1 |
| radius-input | 8 | 6 | 8 |
| radius-card | 12 | 10 | 14 |

- `success`, `danger`, `warning`, `info` each have a matching `-bg`.
- `radius-btn` is 999 (pill) in all three families.
- Every frame sets its family mode, so the same builders render each family's look.
- Product photos reuse image fills that already exist in the client's order frames. No ComfyUI images were needed.

**Payment methods:** per client rule, the checkout frames contain **no redesigned payment section**. A dashed placeholder "Payment methods: as old project (no redesign)" stands in its place. The pay button and its disabled reasons stay, because they are part of spec §2.

| Section (node) | Contents | Ecommet | Elora | Souqify |
|---|---|---|---|---|
| 0 · Index `105:107653` | existing-unchanged list, token notes, modal/sheet motion notes (`105:107620`) | — | — | — |
| 1 · Address modal desktop `97:102078` | 8 states: add, edit, validation errors, phone invalid, map detecting + approximate notice, country dropdown open + search, saving, Arabic RTL | sheet `97:100527` (modals 97:99777…97:100440) | sheet `97:101294` (97:100544…97:101207) | sheet `97:102061` (97:101311…97:101974) |
| 2 · Address bottom sheet 375 `97:104771` | same 8 states as mobile bottom sheets | 97:102080…97:102871 | 97:102977…97:103768 | 97:103874…97:104665 |
| 3 · Checkout address step `98:101810` | desktop page; states (empty, 3 shipping errors, pay disabled ×3); mobile page; mobile states | 98:100243 · 98:100522 · 98:100610 · 98:100707 | 98:100761 · 98:101058 · 98:101146 · 98:101243 | 98:101297 · 98:101571 · 98:101659 · 98:101756 |
| 4 · Login/Register, Forgot/Reset `99:102820` | desktop login+register, desktop forgot+reset, mobile login, mobile register, mobile forgot+reset | 99:100863 · 99:101145 · 99:101342 · 99:101406 · 99:101485 | 99:101528 · 99:101813 · 99:102013 · 99:102077 · 99:102156 | 99:102199 · 99:102459 · 99:102634 · 99:102698 · 99:102777 |
| 5 · Orders & Order details `100:104667` | orders (chips incl. Refunded, search, actions, empty) D/M; order details + tracking + request return ("2 left") + invoice link D/M | 100:101877 · 100:102212 · 100:102522 · 100:102674 | 100:102812 · 100:103150 · 100:103463 · 100:103617 | 100:103757 · 100:104070 · 100:104358 · 100:104524 |
| 6 · Profile, Addresses, Wishlist `101:105919` | profile view/edit + change password + delete account D/M; addresses D/M; wishlist D/M (Ecommet only) | 101:103157 · 101:103445 · 101:103573 · 101:103845 · 101:103953 · 101:104234 | 101:104351 · 101:104642 · 101:104772 · 101:105047 | 101:105157 · 101:105423 · 101:105556 · 101:105806 |
| 7 · Returns `102:108273` | returns list D/M, return detail (thread, reply 10–2000 chars, photos ≤5 MB, video ≤50 MB, withdraw) D/M, request return form D/M | 102:104664 · 102:104937 · 102:105046 · 102:105336 · 102:105455 · 102:105748 | 102:105879 · 102:106155 · 102:106266 · 102:106559 · 102:106680 · 102:106976 | 102:107109 · 102:107360 · 102:107474 · 102:107742 · 102:107866 · 102:108137 |
| 8 · Notifications, Invoice, Modals `103:109520` | notification prefs D/M; printable invoice D/M; modals desktop (cancel step 1, cancel step 2, review, delete account, delete address); same as mobile bottom sheets | 103:106473 · 103:106759 · 103:106881 · 103:107097 · 103:107273 · 103:107488 | 103:107499 · 103:107788 · 103:107912 · 103:108131 · 103:108307 · 103:108522 | 103:108533 · 103:108797 · 103:108924 · 103:109118 · 103:109294 · 103:109509 |

**Existing, unchanged (referenced, not redrawn):**
- **Ecommet:** profile 1:1833; orders 1:1871 (+ status tabs); order details 1:2241 / 1:2126 / 1:2014; mobile account 1:3848–1:4226.
- **Elora:** orders 1:18739 / 1:19347; order details 1:18987; tracking 1:19981 / 1:20213; favorites 1:18588 (+ mobile).
- **Souqify:** orders 1:75734 / 1:75862 / empty 1:76132; tracking 1:75976 (+ mobile); order details m 1:80745; favorites 1:75718 (+ mobile).
- **Superseded:** the old address modals (Elora 1:20817 / 1:22912 / 1:22988, Souqify 1:76266 / 1:80427 / 1:80503) and the Ecommet checkout address fields (1:1520). They stay in the file but are replaced by sections 1–3.

**Not covered here:**
- Verify-email pages (SF-*-025c): not in spec §3.
- CMS / 404 / support pages.
- Wishlist for Elora and Souqify: their existing Favorites frames are kept.
- The Souqify footer still reads "Connect with ELORA". That is the client's existing component, left unchanged by rule.

---

# Storefront motion (page "Storefront motion" `108:10147`)

Motion is layered on top of the existing designs and follows `07-motion-guidelines.md`. The original frames are only referenced by node id, never edited.

| Section (node) | Frames |
|---|---|
| Home motion specs `108:101124` | Legend & tokens `108:101117`. One frame per home: Ecommet `108:99698`; Elora v1–v6 `108:99788`, `108:99924`, `108:100033`, `108:100142`, `108:100251`, `108:100360`; Souqify v1–v6 `108:100469`, `108:100577`, `108:100685`, `108:100793`, `108:100901`, `108:101009`. |
| Inner-page motion specs `109:99873` | Ecommet `109:99697` · Elora `109:99751` · Souqify `109:99812`. Each covers listing/filters, product gallery, cart swipe-to-remove, checkout, account, and the Storefront-additions modal/sheet enter/exit and drag-to-dismiss. |
| Smart-animate prototypes `110:99830` | **Hero:** `110:99697` → `110:99708` (6 s autoplay, drag). **Spotlight card-fan:** `110:99723` ↔ `110:99731`. **Add-to-cart / fly-to-cart:** `110:99743` → `110:99757` → `110:99771`. **Bottom sheet:** `110:99788` → `110:99801` → `110:99814` (drag to dismiss). These four are set as page flow starting points, so Present plays them. |

**What each home spec covers, section by section (with node + y):**
- Reveal and stagger.
- Swipe module (rail with snap or free mode, arrows at the section header on desktop, progress bar on mobile).
- Hero autoplay with progress.
- Marquee.
- Flash-sale countdown flip.
- Grid reveal and load-more.
- Product-card hover, fly-to-cart, and mobile gestures.

**Spotlight:** limited to where the card-fan `1:30232` is used. The Elora v5 Best Seller (`1:28861`) is a staggered grid, not a card-fan, so it is specified as a normal rail.

---

## Batch 3: storefront leftovers, Affiliate & Owner, Central Website additions, Emails & printables (2026-11-25)

All frames are new and sit on our own pages. No client page, frame, component, variable or style was edited; client components (store headers/footers/bottom navs, Central Navbar `12:42345` and FOOTER `12:42512`) are only instanced.

### 1. Storefront leftovers on page "Storefront additions" `97:10147`
One section per family. Desktop frames are 1440 wide, mobile frames 375. Souqify mobile frames are Arabic RTL. All colours come from the `Storefront` collection modes.

| Family | Section | Verify email | CMS page | Support + newsletter | 404 | Store not launched | Maintenance | Suspended | Payment redirect | Payment not completed | Toast board | 500 | 419 | 403 | App landing |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Ecommet | `114:109694` | 114:107621 / m 114:107802 | 114:107865 / m 114:108055 | 114:108109 / m 114:108353 | 114:108479 / m 114:108666 | 114:108727 / m 114:108755 | 114:108783 / m 114:108812 | 114:108841 / m 114:108864 | 114:108887 / m 114:109089 | 114:108918 / m 114:109120 | 114:109173 / m 114:109243 | 114:109313 / m 114:109337 | 114:109361 | 114:109385 | 114:109409 / m 114:109612 |
| Elora | `115:110986` | 115:108883 / m 115:109067 | 115:109132 / m 115:109325 | 115:109381 / m 115:109628 | 115:109756 / m 115:109946 | 115:110009 / m 115:110037 | 115:110065 / m 115:110094 | 115:110123 / m 115:110146 | 115:110169 / m 115:110374 | 115:110200 / m 115:110405 | 115:110460 / m 115:110530 | 115:110600 / m 115:110624 | 115:110648 | 115:110672 | 115:110696 / m 115:110902 |
| Souqify | `115:112959` | 115:110988 / m 115:111147 | 115:111215 / m 115:111383 | 115:111442 / m 115:111664 | 115:111795 / m 115:111960 | 115:112026 / m 115:112054 | 115:112082 / m 115:112111 | 115:112140 / m 115:112163 | 115:112186 / m 115:112366 | 115:112217 / m 115:112397 | 115:112455 / m 115:112525 | 115:112595 / m 115:112619 | 115:112643 | 115:112667 | 115:112691 / m 115:112872 |

Specs:
- **Store states (store not launched, maintenance, suspended, errors, payment redirect)** use a minimal store bar: store mark, store name, language switch, and a "Powered by Nogrgr" footer. They have no header, cart or footer, because the store cannot be browsed in these states.
- **Suspended copy** never shows the reason to customers. Store owners get a "sign in to your dashboard" hint instead.
- **Gateway blocked** is shown as the "Online payment unavailable" toast on each toast board.
- **Toasts:**
  - Placement is top-end with a 24 px offset on desktop, and full width minus 32 px (343 px) on mobile.
  - They auto-dismiss after 5 s. Error toasts stay until dismissed.
  - Swipe or mouse-drag to dismiss, following 07-motion-guidelines.
  - Arabic toasts mirror the icon and close positions.
- **Payment redirect spinner:**
  - Rotates once per 1 s, linear.
  - With reduced motion it becomes a 1.2 s opacity pulse.
  - The "Not redirected? Continue to payment" link appears after 5 s.
- **Payment methods** are never redrawn. Checkout and payment pages keep the old project's section.

### 2. Affiliate & Owner on page "Affiliate & Owner" `118:10147`
- **Tokens:** the Admin collection in Light mode. The front-end affiliate tokens import admin.css, so no extra mode was added. The logo tag reads PARTNER or OWNER.
- **Components:** Affiliate Sidebar `118:99699` and Owner Sidebar `118:99795`, both in section `118:99910`. They are built from Sidebar Item `35:11405` and Logo `35:11335`.
- **Auth, SSO and errors (section `119:101279`):**

  | Row | Frames |
  |---|---|
  | AFF-001 Login | 119:100054 · error (pending / throttled) 119:100128 · m 119:101143 |
  | AFF-002 Register | 119:100207 · validation 119:100321 · m 119:101191 |
  | AFF-003 Pending | 119:100433 |
  | AFF-004 Forgot | 119:100492 · sent 119:100552 |
  | AFF-005 Reset | 119:100603 · invalid token 119:100669 |
  | AFF-006 Verify | 119:100720 · expired 119:100776 |
  | AFF-007 SSO | 119:100823 · invalid / used / expired 119:100873 |
  | AFF-015 Errors | 403 119:100939 · 404 119:100989 · 419 119:101040 · 500 119:101091 |

- **Affiliate panel (section `121:103290`):**

  | Row | Frames |
  |---|---|
  | AFF-008/009 Dashboard | 121:100334 · m 121:102977 |
  | AFF-010 Links & promo codes | 121:100742 |
  | AFF-011 Conversions | 121:101191 · m 121:103181 |
  | AFF-012 Payouts | 121:101567 |
  | AFF-012a Request payout | modal 121:101861 · validation 121:103254 · bottom sheet m 121:103107 |
  | AFF-013 Profile | 121:102088 |
  | AFF-013a Payout details | 121:102328 |
  | AFF-013b Password | 121:102571 |
  | AFF-014 Notifications | 121:102771 |

  The payout amount is validated between the $50.00 minimum and the available balance. Changing payout details holds payouts for 48 h.
- **Owner portal (section `122:105572`):**

  | Row | Frames |
  |---|---|
  | OWN-001 My stores | 122:102288 · m 122:105386 |
  | OWN-002 Select store | 122:105321 |
  | OWN-003 Subscriptions | 122:102615 |
  | OWN-004 Domains | 122:103025 |
  | OWN-004a Add domain | modal 122:103429 · validation 122:103792 · bottom sheet m 122:105483 |
  | OWN-004b Edit domain | 122:103838 |
  | OWN-004c DNS check result | 122:103882 |
  | OWN-005 Invoices | 122:104137 |
  | OWN-006 Profile + linked Google / Apple | 122:104562 |
  | OWN-007 Add another store | 122:104860 |
  | OWN-008 Notifications | 122:105109 |

  Store cards open the store panel through SSO. Their states are Live, Trial, Not launched and Suspended.
- **Mobile pattern:** a dark top bar with menu, logo and bell. Modals become bottom sheets with a grabber, and actions stack full width with the primary action on top.

### 3. Central Website additions on page "Central Website additions" `125:10147`
- **Visual language** copied from Central Website `12:41054`:
  - white canvas with a soft #FF522C radial glow;
  - Outfit; ink #41251E; accent word in #FF522C;
  - white cards (radius 24, shadow 0 8 40 rgba(65,37,30,.08));
  - inputs 48 high (56 when filled), radius 8, #C4C4C4 stroke;
  - buttons 48 high, radius 12;
  - client Navbar and FOOTER (White / mobile) instances.
- **Registration and onboarding flow (section `125:12455`):**

  | Row | Frames |
  |---|---|
  | WEB-016a Social popup | success 125:10900 · cancelled 125:10912 |
  | WEB-016b Plan choice | 125:10149 · m 125:10421 |
  | WEB-016c Payment | 125:10606 · m 125:10779 (payment methods = labelled "as old project" box) |
  | WEB-017 Verify email | 125:10925 · resent 125:11066 · m 125:11200 |
  | WEB-018 Account | 125:11277 · m 125:11433 |
  | WEB-018a Store | 125:11537 · validation 125:11701 · custom domain + DNS check + profit % + categories 133:12577 · m 125:11865 |
  | WEB-019 Redirect | 125:11977 |
  | WEB-019a Success | 125:12081 · m 125:12227 |
  | WEB-019b Failed | 125:12321 |

  WEB-021 and 021a–d are mapped to the existing onboarding wizard (Countries → Languages → Theme → Launch) at 82:99932 / 82:99996 / 82:100084 / 82:100161, because it runs inside the store panel.
- **Content, auth and errors (section `127:13244`):**

  | Row | Frames |
  |---|---|
  | WEB-004 Theme preview | desktop view 127:11426 · mobile view 127:11559 |
  | WEB-011 Privacy | 127:11603 · m 127:11737 |
  | WEB-012 Static page | 127:11792 |
  | WEB-013 Blog list | 127:11911 · m 127:12095 |
  | WEB-014 Blog post | 127:12209 · m 127:12370 |
  | WEB-023 Forgot | 127:12477 · sent 127:12646 · m 127:12586 |
  | WEB-024 Reset | 127:12754 · invalid 127:12867 |
  | WEB-027 404 | 127:12974 · m 127:13076 |
  | WEB-028 Maintenance | 127:13205 |
  | WEB-029 500 | 127:13129 |
  | WEB-030 419 | 127:13167 |

### 4. Emails and printables on page "Emails & printables" `129:10147`
- **MAIL-001 layout system (section `129:10390`):** spec 129:10149 · central en 129:10162 · tenant en 129:10215 · tenant ar RTL 129:10286 · central ar RTL 129:10357.
  - 600 px table-based layout with inline CSS.
  - Preheader → 4 px brand bar → header (mark, name, reference) → body → footer (reason, address, Help · Privacy · Unsubscribe on marketing emails only, "Powered by Nogrgr" on tenant emails).
  - **Central brand:** #FF522C, "Nogrgr".
  - **Tenant brand:** store logo, name and primary colour.
  - **RTL** mirrors rows; numbers and IDs stay LTR.
- **Templates MAIL-002…020 (section `130:11231`):**

  | Row | Frame | Row | Frame |
  |---|---|---|---|
  | 002 | 130:10175 | 012 | 130:10687 |
  | 003 | 130:10213 | 013 | 130:10758 |
  | 004 | 130:10266 · customer 130:10304 | 014 | 130:10820 |
  | 005 | 130:10343 | 015 | 130:10889 |
  | 006 | 130:10382 | 016 | 130:10968 |
  | 007 | 130:10436 | 017 | 130:11011 |
  | 008 | 130:10486 | 018 | 130:11069 |
  | 009 | 130:10538 | 019 | 130:11122 |
  | 010 | 130:10582 | 020 | 130:11180 |
  | 011 | 130:10635 | | |

- **Templates MAIL-021…042 (section `131:11409`):**

  | Row | Frame | Row | Frame |
  |---|---|---|---|
  | 021 | 131:10338 | 032 | 131:10875 |
  | 022 | 131:10386 | 033 | 131:10930 |
  | 023 | 131:10440 | 034 | 131:10978 |
  | 024 | 131:10494 | 035 | 131:11023 |
  | 025 | 131:10531 | 036 | 131:11071 |
  | 026 | 131:10579 | 037 | 131:11125 |
  | 027 | 131:10631 | 038 | 131:11159 |
  | 028 | 131:10692 | 039 | 131:11207 |
  | 029 | 131:10733 | 040 | 131:11262 |
  | 030 | 131:10786 | 041 | 131:11316 |
  | 031 | 131:10833 | 042 | 131:11367 |

  Approved / rejected and finished / failed pairs show the second variant as a notice block inside the same frame.
- **Printables (section `132:11596`):**

  | Row | Frame | Notes |
  |---|---|---|
  | MAIL-043 Customer tax invoice | 132:10518 | A4 794×1123; bilingual title; e-invoice QR |
  | MAIL-044 Order receipt / packing slip | 132:10888 | Barcode, pick checkboxes, signatures |
  | MAIL-045 Shipping label | 132:11044 | 4×6 in (384×576); Code 128 barcode, routing code, QR |
  | MAIL-046 Subscription / platform invoice | 132:11403 | PAID stamp |
  | MAIL-047 Payout / settlement statement | 132:11491 | |

  Print CSS: A4 with 12 mm margins, black-only label.

---

## Batch 4a: coverage fill and storefront gaps (2026-11-25)

**Coverage column.** All 191 ADM and 125 VEN rows in `05-page-coverage.md` now carry node ids from the batch-1 and batch-2 index (Figma column only, checked against a backup).

**Storefront gaps on "Storefront additions" `97:10147`**
- **Section `135:112754`:**
  - search autocomplete (Elora `135:111544` / m `135:111756`; Souqify `135:112185` / m(ar) `135:112339`);
  - image-search modal (upload, results with match %, mobile sheet with camera/gallery choice) for each family;
  - reviews modal (summary bars, filters, sort, list, write-review state, mobile sheet) for each family.
- **Section `137:114701`:**
  - **Delivery-estimate popover and sheet**, driven by the Delivery popup days distribution (days 1–10, sum 100%): Ecommet and Souqify. Elora keeps its client frame `1:23053`.
  - **Mini-cart drawer** (420, slides in from the inline-end) and mobile "item added" sheet: Elora and Souqify.
  - **Souqify empty cart**, desktop and mobile (ar).
  - **Ecommet order success**, desktop and mobile.
  - **Listing-variant annotation frames** for search results, new in, best selling, top rated and offers. Each sits over a PNG snapshot of the client listing (`1:1191`, `1:17172`, `1:75532`, all unchanged) and records the title, route, default sort, filters, empty state and badge.
- **Motion:**
  - Drawer and sheet enter in 320 ms with ease-out.
  - Swipe or drag down dismisses sheets.
  - The image-search scanning line loops every 1.2 s; with reduced motion it becomes a static "Searching…" label.
  - The success check scales in over 400 ms with confetti; with reduced motion it fades in.
