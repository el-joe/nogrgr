# Elora base Home - Mobile Large spec

Figma file `2wXSYbP5gQSJHiVQWJJpSf`, page "Elora Mobile – Large", frame "Home – Mobile Large", node `232:10148`
(https://www.figma.com/design/2wXSYbP5gQSJHiVQWJJpSf/?node-id=232-10148). It is a clone of `1:21125` (375x5137, untouched); the clone is 375 x ~6,880 and is a vertical auto-layout.
Same colors, fonts (Outfit), icons and images as the original; only scale, spacing and layout changed. Images keep their original fill mode (use `object-contain` on the site per project rule).

Global: 375 wide, 16px side padding, 24px between sections, min tap target 44px, no visible text under 12px.

## Layout changes (final decisions, override the first brief)

- Categories (node `232:10150`): ONE row, horizontal Swiper-style slider (no second row, no wrap). Tile 100 wide, 80px round icon, 14px label centred (max 2 lines), 8px icon-label gap, 12px gap between tiles, 20 tiles. Slider viewport runs from x16 to the screen edge (359 wide, clipped), so about 3.3 tiles show and the 4th tile peeks about 23px. 7 pagination dots below (active 18x6 pill orange, others 6px grey).
- Recommended For You grid (node `232:10373`, confirmed): original 2-column grid, card 164 wide, 15px column gap, 16px row gap, 164px-tall image, larger text (title 14, price 17, meta 13).
- Buy together: two stacked full-width cards (343 x 112), 96px image, 44px "+" connector centred between them; the two 20% ribbons are scaled to 50% and placed in the top corners.
- Product rails (Flash, Trending, Best Seller, New Arrivals, and the orange "Recommended for You" band): exactly 2 cards per view in a Swiper-style slider. Viewport 343 wide, card 165, gap 12, clip at the viewport, more cards beyond the 2 visible (no peeking third card), pagination dots under each rail (6px dots, active 18x6 pill; orange, white on the orange band).

## Before -> after (px)

| Component | Property | Before | After |
|---|---|---|---|
| Header | avatar | 44 | 48 |
| | greeting font | 18 | 20 |
| | address font / chevron | 12 / 16 | 14 / 18 |
| | bell button | 40 | 44 |
| | header block height | 147 (+search overlap) | 151 (+search overlap), outer 183 |
| Search bar | height | 54 | 56 |
| | text / icon / camera | 16 / 20 / 33x24 | 16 / 22 / 38x28 |
| Banner strip | slide size / height | 329x106 | 319x128, gap 12, next slide peeks |
| Trust strip | font / icon / height | 9.9 / 15 / 31 | 13 / 18 / 44 |
| Section titles | font | 14 (Categories 18) | 20 |
| | "see all" font / row height | 12 / 18 | 14 / min 44 |
| | title icon | 17x10 | ~1.4x scale (about 24x14) |
| Categories | layout | 2 horizontal scroll rows, tile 56 wide | 1 horizontal slider row, tile 100 wide, 12 gap, 4th tile peeks |
| | icon | 34 | 80 (round) |
| | label | 10 | 14 medium, centred, max 2 lines |
| Chips | height | 32 | 40 min |
| | text / icon | 12 / 16 | 14 / 18 |
| | padding | 12x8 | 14x10, gap 10 between chips, icon gap 8 |
| Rail card | width / image | 132 / 115 high | 165 / 165 high |
| | title / weight | 12 / 10 | 14 / 12 (see deviations) |
| | subtitle | 10 | 13 |
| | rating text / stars | 8 / 7x6 | 13 / 13x12 |
| | price | 12 | 17 bold |
| | old price / discount | 8.8 / 8 | 13 / 13 (discount wraps to its own line) |
| | delivered / only-left | 8 / 7.5 | 13, icon 14 |
| | cart button | 36x28 | 36x36, icon 20 |
| | info padding / gap | 4 / 2.5-4 | 8 / 6 |
| | card height | 200 | 306 |
| | rail gap | 16 | 12 |
| Flash banner | banner image | 375x39 | 375x56 |
| | countdown font / chip | 14 / 34x26 | 18 / ~44x34 (padding 8x6) |
| | code chip | 10.7 text, 26 high | 13 text, padding 12x9 |
| Recommended band (orange) | mini cards | 72x109, 5.4-8 text | 165 wide rail card, same specs as rail card |
| | title / padding | 14 / 8 | 20 / 16 top, 20 bottom |
| Buy together | title | 14 | 20 centred |
| | card | 144x55 side by side | 343x112 stacked, padding 8, gap 12 |
| | image | 63x55 | 96x96 |
| | title / price / meta | 8-12 | 14 / 16 / 12 |
| | "+" connector | 16 | 44 |
| | ribbons | 79x37 at side | 40x19 (50%) top corners |
| Recommended grid card | layout | 2 col, 164x238 | 2 col, 164 wide (unchanged), 15 column gap |
| | image | 143 high | 164 high |
| | title / weight / subtitle | 12 / 10 / 10 | 14 / 12 / 13 |
| | rating / stars | 8 / 7x6 | 13 / 13x12 |
| | price / old / discount | 12 / 8.8 / 8 | 17 bold / 13 / 13 (discount wraps to own line) |
| | delivered | 8 | 13, icon 14 |
| | cart button | 36x28 | 40x36 |
| | row gap | 12 | 16 |
| Footer | social icons | 19 | 28 (16 gap, 44 high row; use a 44px hit area) |
| | link text / row | 12-15 / 15 | 16 / 44 tall rows, 64 between columns |
| | version / copyright | 10 / 8 | 12 / 12 |
| | top padding / gap | 24 / 24 | 32 / 20 |
| Bottom tab bar | height | 80 | 88 |
| | icons / labels | 24 / 12 | 28 / 12 |
| | cart bubble | 74 | 80, cart icon 28 |

## Deviations

- Rail card title is 14 (not 15) because title plus the weight tag ("200g") must fit one row in the 149px content width; a 15px title overflows.
- Greeting 20 and address 14 (the Figma original already had 18 and 12, so the brief's 16/12 values were adjusted upward).
- The original frame had the footer overlapping the last grid row; the clone is a clean flow with no overlap.
- To resize inner parts, all component instances inside the clone were detached (clone only). The "Mobile Card" is therefore not linked to the master in this frame.
- Pagination dots and the 44px "+" connector are new elements; the white active dot on the orange band uses white, other dots use the brand orange from the discount text and light grey (#D9D9D9).
- Social icon hit area 44px is a spec note (the glyph is 28px); the Figma row is 44px high.

## Product rail breakpoints (home + product page, final)

Home rails (`elora/v1/rail.blade.php`) and PDP rails (`pdp-rail.blade.php`) use the same Swiper steps; every step carries gap, offsets and free mode (Swiper breakpoints do not stack).

| Width | slidesPerView | gap | Mode |
|---|---|---|---|
| <520 | 2 (group 2) | 12 | paged, dots, large mobile card |
| 520-639 | 2.5 | 16 | free mode, dots |
| 640-767 | 3.2 | 16 | free mode, dots |
| 768-839 | 3 | 16 | free mode, normal Elora card |
| 840-919 | 3.4 | 16 | free mode |
| 920-999 | 3.8 | 16 | free mode |
| 1000-1023 | 4 | 16 | free mode |
| 1024-1149 | 4 | 24 | free mode |
| 1150-1279 | 4.5 | 24 | free mode |
| >=1280 | 5.9 (6th clipped, ~210px card at 1440) | 24 | free mode |

The large mobile card scale (165px image, 17px price) applies only below 768px; 768-1023 restores the Figma desktop card for `.v1-home .v1-rail`. Edge offsets per rail kind (tabs 16/38, flash 12/40, others 16/0 below/above 1024) are part of each step; below 768 the offsets are 0 and the rail has 16px side padding.

## Tablet (768-1023) adaptations of the home sections

- **Recommended For You grid** (hidden from 1024, the desktop shows the rail): <768 = 2 columns (unchanged); 768-839 = 3 equal columns, 840-1023 = 4 equal columns (`repeat(N, minmax(0, 1fr))`, 16px gap), Figma desktop card style; no card passes ~250px (230px at 768, ~201px at 900). Card photos stay `object-fit: cover` per the client decision in `product-card-cover.css`.
- **Buy together**: the stacked layout (two 112px-high cards, 96px image, 44px "+", marks in the top corners) now applies below 1024px; the row is centred at max 600px; 1024+ keeps the desktop layout.
- **Categories**: one Swiper serves phones and tablets (<1024): one row, 80px round icon, 14px label, 12px gap, 20px title, 44px "see all", dots; tile 100px wide below 768, 110px from 768 (6 fit, the 7th peeks). 1024+ keeps the desktop design (81px icons, not tiny); the old hidden tablet copy of the tiles was removed.
