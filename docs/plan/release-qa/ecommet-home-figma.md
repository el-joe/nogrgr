# Ecommet store home vs Figma (2026-11-28)

The store at `vendor.nogrgr.loc` renders **Elora v1** (theme `elora`, variant 1): Figma nodes `1:15432` (desktop) and `1:21125` (phone) are on the "Elora Variants" page. The separate `ecommet` family (Figma 1:1002) is untouched. Flash sale `1:15595`, navbar / menu `1:20866`.

Checked in Chrome (same-origin iframes at 1440, 1715, 1920 and 375; en and ar). Screenshots: `ecommet-home/`.

## Sections

| # | Section (desktop / phone) | Before | Outcome |
|---|---|---|---|
| 1 | Navbar: menu button, logo, search, language/currency, favourite, cart, account | Close, other icons | Figma icons and spacing |
| 2 | Menu open (1:20989): X "close", 221px category column, 221px grey sub column | Short panel, extra "Shop all" | Matches; Esc / outside click close |
| 3 | Phone header: greeting, place, bell, search | Account icon instead of bell | Matches |
| 4 | Hero 1204 x 387, radius 25, arrows; phone 329 x 106 with peek | Square upload with grey side gaps | Four Figma slides at the exact ratio, Swiper, arrows, drag, RTL |
| 5 | Trust strip | Different icons / sizes | Matches (dark desktop, orange phone) |
| 6 | Product tabs + rail | Tab row pushed off-screen, rail past the container, Show More | Tabs stay put; each tab is its own clipped Swiper; Show More removed; "Fast delivery" tab |
| 7 | Categories (two rows) | One "J" placeholder | Real categories with product photos |
| 8 | Flash sale (1:15595): artwork, countdown, caption, code, cards, arrows | No artwork, no code | Matches, with bolt pattern; code copies on click |
| 9 | Trending Now | Different heading and cards | Matches |
| 10 | Category band (desktop) | One item, left aligned | Matches |
| 11 | Recommended band (phone) | Missing | Added |
| 12 | Best Seller, New Arrivals | Icons / cards differed | Matches |
| 13 | Shop by Category (desktop) | Missing (no category images) | Added |
| 14 | Buy together (phone) | Missing | Added |
| 15 | Recommended For You (rail desktop, 2-column grid phone) | Rail only | Matches |
| 16 | Free shipping strip + rail (desktop) | Present | Restyled |
| 17 | Badge shelves (5 extra rails) | Shown, not in Figma | Off by default (page builder can switch them on) |
| 18 | Footer | Other columns and icons | Figma columns, social icons, payment images, legal bar |
| 19 | Phone tab bar | Close | Figma icons, 74px cart |

## Remaining differences (data or deliberate)

- Store content: name wordmark instead of the ELORA logo, store's own products, categories (7, not 22), prices, USD flag, payment methods actually accepted (no Fawry), the store's TikTok link.
- Unreviewed products say "No reviews yet" instead of a rating.
- Countdown shows a days box when the sale ends more than 99 hours ahead.
- Strip text reads "Free shipping when you purchase X" only when the shipping rules have a free weight for the country; otherwise a deals line.
- Phone bell opens order updates (there is no storefront notification page). Search camera appears only when image search is enabled. The "V1.0 (001)" app version line is not shown.
- Footer "Payment" links to the purchase protection page (no payment policy page).
- Figma's sale caption typo "electrinics" is spelled correctly in the demo content.
- Figma shows arrows with the first card cut at both ends; rails start at the first card (previous arrow dimmed until used). The tabs rail has no arrows, as drawn.
- The heart appears on card hover / focus only. Corner badges are not drawn on home cards.

## Hero image ratio

Slides are 2408 x 774. An upload with another ratio is shown whole (contain, never cropped) and so has side bars; the banner upload hint states the size. The hero artwork in Figma is low resolution (about 450px wide source), so it looks soft.

## Follow-up changes (same day, client feedback)

- Hero art fills the slide at the 1204 x 387 ratio; product tabs stay fixed and every rail is a Swiper clipped to the container.
- Category menu spans the full viewport (1440 / 1715 / 1920, en / ar, no horizontal scroll); its columns stay on the header container.
- Product card badges come from the badge records (name per language, active switch, platform badge order, store placement); admin and vendor changes show at once.
- Product card photos and "Shop by Category" tiles fill their box (`object-fit: cover`, decisions #131 / #132). Elora base uses one card on every page.
- No card / rail / section shadows or edge fades. Kept: header while scrolled, menus, dropdowns, drawers, modals, sticky bars and phone tab bar, arrow / heart / cart buttons. Category and listing templates belong to the category work.
- Preferences modal: X, Escape and backdrop close it (pointer capture on the sheet header swallowed the X click; fixed for every sheet), no visible scrollbar, searchable Select2 currency list.
- Currencies: 153 offered (all 155 active in dev; KPW and SVC have no rate). Rates from open.er-api.com (daily job / admin sync). Charging stays USD.
- Elora product page: gallery no longer stretches the page sideways (pre-existing).

## Page check (Chrome, 1440 en and 375 ar)

| Page | Cards | Badges | Cover, no shadow, no overlap, no sideways scroll |
|---|---|---|---|
| Home | yes | yes | pass |
| Offers, Best selling, Top rated, Search, Category | yes | yes | pass |
| New in | yes | none (those products have no badge) | pass |
| Product (related) | yes | yes | pass |
| Cart (recommendations) | yes | yes | pass |
| 404 | yes | yes | pass |
| Categories, static pages | no cards | - | pass |
| Favourites, checkout, account | need sign-in; same card component, not opened | - | not checked in browser |

## Dev store data applied

`DEMO_STORE=vendor` + `EloraHomeDemoSeeder`: hero slides, flash artwork / title / end date / products, coupon FLASH30, social links. The old square test banner was deactivated (not deleted). A home layout with the flash code was published; the previous (unused Souqify draft) row is saved at `backend/storage/tenant1/app/private/home-layout-backup.json`.
