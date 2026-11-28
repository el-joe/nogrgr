# Ecommet store category page vs Figma (2026-11-28)

The store at `vendor.nogrgr.loc` renders **Elora v1**, so the frames are the Elora ones: desktop `1:17172`, phone `1:21453`, phone filters `1:22799` (file `2wXSYbP5gQSJHiVQWJJpSf`, read only). The `ecommet` family (Figma 1:1693) and Souqify listings are not touched. The new listing is Elora **v1 only** (`pages/listing-v1`, also serving v1's search, best selling, new in, top rated and offers). Variants 2-6 keep `pages/listing` and `partials/filters`, byte-identical to commit 03ed72a (checked in Chrome through the signed theme preview with elora-2 and elora-5; the store theme was not changed). Shared with them without changing their look: the listing query / filter logic (sale, availability, featured, min-max price), the extra facet counts, and two no-op hooks in `elora/pages.js`.

Checked in Chrome (window 1720 and same-origin iframes at 1440 and 375; en and ar). No console errors. Screenshots: `ecommet-category/`.

## Desktop 1:17172

| # | Section | Before | Outcome |
|---|---|---|---|
| 1 | Navbar | Header component | Home agent's component, unchanged here |
| 2 | Filter sidebar: 259px, flush with the container, 32/24 padding, right border | Inset card with shadow | Matches at x 0 / y 99; shadow removed (client) |
| 3 | Category block with the open category ticked | Child categories only | Current category ticked (unticking goes up one level), children below |
| 4 | Open group with search, "Popular" caption, eight options | All values in a scrolling box, counts cut off | Eight most used, search finds all, "Show more" (20 at a time) / "Show less"; no scrollbars, counts visible |
| 5 | Group order: Brand, Size, Promotional items, Measurements, Color, Price, Gender, Product rating, Pattern, Fabric Type | Attribute order, price buckets | Design order; groups without data are not drawn; extra variations and Availability last |
| 6 | Quick pills: Flash, Top-rated, Fast delivery, Coupon deals, New in | Best sellers / Offers links to other pages | The five pills, as toggles of the current listing |
| 7 | Sort "Most relevant" 177x35 with arrows | "Recommended", other icon | Matches (y 118) |
| 8 | Category circles 80px, 64px image, gap 24, drag | Initials | Category image or a product photo; y 178; swipe / mouse drag, RTL |
| 9 | (not drawn) Back | none | Added on client request, see below |
| 10 | Grid 5 columns, 14px gap, rows 24px, from y 304 | 4-5 columns, other offsets, h1 and breadcrumbs above | Matches (cards x 297, y 304, 321 high); title and breadcrumbs for readers only |
| 11 | Card with summary and "Delivered by" line | Card without them | Reuses the home agent's v1 card (`x-sf::elora.v1.card`) |
| 12 | No load-more button | "See more" button | Next page loads on scroll; the link stays for keyboard, crawlers and retry after an error |
| 13 | Footer | Footer component | Home agent's component |

## Phone 1:21453

| # | Section | Before | Outcome |
|---|---|---|---|
| 1 | Search pill (back, category name, camera) | Logo bar plus a second search pill | Matches; logo bar hidden on listing pages |
| 2 | Quick pills, swipeable, "Top-rated" short label | Other set | Matches |
| 3 | Two-column grid, 164px cards | Close | Matches |
| 4 | Floating "filter / sort by" at the bottom | Above the tab bar | Matches; the tab bar is not drawn and is hidden on listing pages |
| 5 | No circles, no title | Both shown | Matches |

## Phone filters 1:22799

Full screen: logo and round close, groups in the design order (no Category block), eight options plus search, sticky "show ( n result )" and "reset". The count updates while ticking, "show" applies (state in the URL, badge on "filter"), "reset" clears, drag / Esc / close dismiss. Selections made in the sidebar's Category block are kept.

## Back button (client)

Not in the Figma desktop frame. Followed the old Ecommet theme (`multi-vendor/.../themes/ecommet/pages/category.blade.php`): a round chevron tile first in the circles strip, labelled with the parent (or "All categories"). It stays fixed while the circles swipe. On phones the Figma search-pill chevron is the back button. The target follows the category tree (child to parent to top to `/category`), also on direct visits and on leaf categories.

## Filter logic (from the old project)

Values of one variation combine with OR, different groups with AND. Promotional items: on sale, flash sale, featured. Price: minimum / maximum typed in the display currency. Rating: 4 to 1 stars and up. Availability: in stock or out of stock. The sidebar applies on change; the phone sheet applies on "show".

## States

Empty (with reset), loading (grid dims, spinner while the next page loads, skeletons), load error (link shows the error and retries), unknown category (themed 404).

## Shadows

On v1: none on the sidebar, toolbar, circles, grid, search pill or floating filter button; no rail edge fades. Kept: the sort dropdown, sort bottom sheet and filter sheet scrim (floating layers). Variants 2-6 keep their drawn shadows. Product card shadows belong to the card component.

## Remaining differences

- Data: the store has no Brand, Measurements, Gender, Pattern or Fabric variations, no reviews ("No reviews yet", "Top-rated" can be empty), its own categories, products, prices and wordmark.
- Counts beside options, "Show more", Availability and extra variations (Purity) are not drawn; kept for the client's filter requests.
- The camera in the phone search pill appears only when image search is enabled.
- With the tab bar hidden as drawn, phones reach the cart from other pages (back, then the tab bar).
- The sort control is a Select2 styled as the drawn pill (its open list is not drawn).
