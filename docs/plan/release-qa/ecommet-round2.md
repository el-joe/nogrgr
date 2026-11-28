# Ecommet storefront - QA round 2 (frontend)

Store: http://vendor.nogrgr.loc/ (guest). Signed-in pages (checkout, wishlist, register) on the local QA store `qastore2.localhost:8203` (DB `nogrgr_qa`, theme switched to Ecommet).
Method: headless Playwright at 1440 and 375, en and ar; Figma read with the Figma MCP (read only).
Scripts: `backend/tests/e2e/themes/ecommet-round2.mjs` (page crawl). Screenshots: `docs/plan/release-qa/ecommet-round2/`.
Crawl result (22 pages x 4 modes): no console / page errors, no failed requests, no dead `#` links, no raw translation keys, no horizontal overflow; 38 internal links checked, none broken.

## Fixed

| Page / section | Defect | Fix |
| --- | --- | --- |
| Footer, phone (m 1:2656) | Order was columns, We accept, logo, socials; colour #3b3b3b; white strip under the footer | Columns, divider, Connect with + socials, We accept, copyright; #242424, 12px grey links; logo hidden; spacer in footer colour |
| Home, phone product tabs | "5-Star Rated" tab disappeared when empty | All three design tabs stay; empty tab shows a message |
| Home, deals | Heading weight 600 (Figma 900), link weight, wrong lightning / clearance icons | 900 / semibold; Figma icons `deal-lightning`, `deal-clearance` |
| Home, "Why choose" | "Delivery guarantee" sat 3px off the row | Aligned |
| Product, "You might also like" (1:3380) | 10 cards, no See More | 15 cards (3 rows of 5) + See More (`?related_page=`) |
| Product, phone (m 1:3073) | No tabbed recommendations, no title expand chevron, gap under the sticky Add to cart bar | Recommended + category tabs, chevron when the title is cut, bar sits on the nav |
| Product gallery | Counter stayed 1/9 and thumbnail highlight stayed on the first thumb when a variant opened another image | Counter and highlight follow the current slide |
| Header, Categories | With a mouse, clicking "Categories" closed the menu hover had just opened | First click keeps it open |
| Cart drawer | Blocked cart: "to checkout" was silently dead (`pointer-events-none`) | Says why and opens the cart; test added |
| Cart drawer / dialogs | Back-forward cache could restore an open overlay and scroll lock | Overlays reset on restore |
| Cart, phone (m 1:3210) | No tabbed recommendations | Same tab strip as the product page |
| Cart empty (m 1:3329) | No trust row; button dark instead of outlined | Added |
| Best selling (1:1191) | Filters wrapped to a second row at 1440 | Category label visually hidden below 2xl |
| Tests | Stale assertions after the payment-badge images and new icons | Updated |

## Header cart drawer report (user bug)

- "to checkout" / "Go to cart" doing nothing: **not reproduced**. Real mouse clicks navigate after product-page, card variant-modal and listing adds, after Back, after qty change and untick, at 1024 / 1280 / 1366 / 1440, RTL and with touch; nothing covers the buttons. The one silent case found in code (blocked cart) is fixed above. Not tested: browser zoom and a page left open across an asset rebuild.
- "$1.75 to checkout" then $6.75: dev data had **no shipping rule at all** until be-lifecycle added "QA Gulf" ($5), so the first figure had no shipping. A real related inconsistency (Store API priced the store default country, storefront the visitor country) was fixed by be-lifecycle. Drawer and `/cart` totals re-checked equal: en/ar, guest and signed in.
- Unticking items in the drawer does not change the total or what is checked out (selection only drives "Remove selected"). Open - product decision.

## Open (data or owner decision, not changed)

- Hero banner looks stretched: the uploaded banner is 447x447 in a 1440x279 slot and commit 337576a set `object-fill` on purpose. Upload 1440x279, or confirm switching back to contain.
- Home kicker and flash band text "asdasd": flash-sale title data.
- Home best-selling shows 2 items: only 2 products carry the manual Best-selling badge (data). Empty stars: no reviews. Sold count / "#Best-Selling Item" tags appear only with data.
- Owner commits that differ from Figma: one card size everywhere (4290b55; Figma deal card 224 wide, phone deal card borderless), cart picks full width below the summary (960c64c; Figma left column 3x3), no forced "Best-Selling" label on best-selling cards, cart drawer 260px (Figma 220).
- Phone legal links stay in the footer (not in m 1:2656) so Terms / Privacy remain reachable.
- Phone checkout keeps footer and bottom nav; Storefront-additions frame 98:100610 ends at the Pay button.

## Figma coverage

Compared: 1:1002, 1:2656 (+ header 1:1072, deals 1:1005, flash 1:1154, footer 1:2658), 1:1191, 1:1240, 1:1348, 1:1520 / 98:100243 / 98:100610, 1:1662, 1:1693, 1:1796, 1:2979, 1:3073, 1:3210, 1:3329, 1:3380, 1:3540, 1:4267.
**Not compared with Figma** (live screenshots checked only, or not opened): 1:1163 autocomplete, 1:2646 pop-up, 1:2710, 1:2764 component board, Storefront-additions modals (reviews, delivery estimate, address), auth 99:*, wishlist 101:103953, CMS / support / 404 / payment frames 114:*. Account, orders and returns pages belong to be-lifecycle.
Not re-tested this round: reduced motion, Stripe payment, image search.
