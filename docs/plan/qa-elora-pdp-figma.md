# QA: Elora base (variant 1) product page vs Figma 1:16245 (desktop) / 1:21580 (phone)

Store: http://vendor.nogrgr.loc/ (products 53 = reviews, 190 = colour chips + video, 1 = long option names).
Screenshots in `.playwright-mcp/`: Figma `pdp-figma-desktop-full.png`, `pdp-figma-mobile-full.png`; before `pdp-before-*.png`; after `pdp-after-*.png`
(desktop, mobile top / mid / bottom, RTL), gallery `pdp-gallery-auto-{square,portrait}-{375,1440}.png`, rails `pdp-rails-{768,1140,1440}.png`,
`pdp-after-mobile-rails.png` (+ `home-mobile-rail-ref.png`), chips `pdp-chips-{before,after}-{1440,375}.png`, `pdp-chips-after-rtl-375.png`.

Only variant 1 changed: view `themes/elora/pages/product-v1.blade.php` (picked by `StorefrontView::resolve` as `pages.product-v1`, like home / listing), styles
`resources/css/storefront/elora-pdp.css` (`.epd-*`, imported by `storefront-elora.css`). Variants 2-6 keep `pages/product`.

## Layout (matches Figma except where noted)
- Desktop: columns 697 / 542, gap 48; gallery with 7-up thumbs (88x80, r8, dimmed inactive, orange active outline), "Play Video" thumb, zoom button, 40px arrows, "#Best-Selling Item" badge; 32px title, rating, price (compare 20 / price 24 / -30% OFF tag), delivery line, colour / size chips, 78x28 qty select + "Added", 410x48 black add-to-cart + heart + share (48 circles with shadow), 2x2 trust cards (payment badges use the card images where the project has them), Product Details (32px, 125px clamp, "See more"), reviews (20px count, 24px score, 32px black stars, verified pill, 3 previews, 213x48 "See all reviews"), three rails.
- Phone: back / search / heart / share header (store header hidden), gallery with counter and promo strip, buy info, green free-shipping band, stacked trust cards, details, reviews, rails, fixed qty + add-to-cart bar. The design has **no tab bar** on the PDP, so the tab bar and its spacer are hidden on this page (phones, variant 1); page and footer keep room for the bar; the video popup floats above it.

## Gallery (user request, deviates from Figma's fixed 694x395 / 343x257 box)
The main slider follows the photo's own ratio: image / poster `width: 100%; height: auto`, `object-fit: contain`, whole photo visible, no crop, no grey bars. Swiper `autoHeight` sizes the wrapper to the active slide and is re-measured on image load, video metadata, slide change and resize (`core/motion/gallery.js`, only when the gallery sets `autoHeight`). Width/height attributes give the first paint a ratio; photos taller than 80vh are capped (letterboxed only then). Video slides: width 100%, 16:9 until the video's own ratio is known. Arrows, counter, badges, zoom button and the thumbnail strip stay overlaid / below. Thumbnails keep `contain` on their grey tile; the active thumb is an inset outline so the image fills the tile.
Measured (every slide, video included): square product 190 -> 1440: slide 694x694 = image 694x694; 375: 343x343 = 343x343. Portrait (dev DB only has square photos, so a 600x900 canvas image was swapped in on product 53) -> 1440: 694x720 = 694x720 (80vh cap); 375: 343x515 = 343x515. Wrapper height equals the slide in every case.

## Rails
Three rails (Similar items / Shoppers also viewed / Recommended For You), Swiper "rail", touch + mouse drag + trackpad, RTL. Cards per view (Swiper `slidesPerView` + `spaceBetween` per breakpoint; breakpoints do not stack so each step carries the full free-mode set):

| viewport | cards | gap | measured (product 53) |
|---|---|---|---|
| < 520 | 2 (paged, dots) | 12 | 375: 166 wide, image 166 |
| 520-767 | 2.5 / 3.2 (free mode) | 16 | 640: 179 wide |
| 768-999 | 3 / 3.4 / 3.8 | 16 | 768: 3 cards, 235 wide; 900: 244 |
| 1000-1149 | 4 | 16 / 24 | 1024: 4, 218; 1140: 4, 247 |
| 1150-1279 | 4.5 | 24 | 1279: 248 |
| >= 1280 | 5.9 (6th clipped) | 24 | 1280: 183; 1440 and 1920: 211 (Figma 210) |

No card is wider than 250px at any width (checked 375, 520, 640, 767, 768, 900, 1023, 1024, 1140, 1279, 1280, 1440, 1920; RTL 1140 identical). Below 768 the cards are the home page's mobile cards: the PDP rails wrapper carries `v1-home`, so the mobile block of `elora-v1.css` styles both (home 375 card vs PDP card: 165.5 wide, 165.5 image, 14px / 500 name, 17px / 700 price, 13px old price, 36x36 heart + cart). From 768 up the cards use the Figma desktop card (183px image, 16px name, 18px price; the phone rules below 1024 are put back inside `.epd`). Home rails are untouched (home 375 slide 166, 1440 slide 676 as before).

## Variant chips
Chips are `min-height` (28 phone / 32 desktop) with `padding: 6px 12px`, radius per Figma, `max-width: 100%`, centred text with `line-height: 1.3` and `overflow-wrap: anywhere`; long names wrap inside the chip and clamp to 2 lines with an ellipsis (full name in the `title`); chips wrap onto new lines with an 8px gap. Selected = orange fill; hover / focus-visible (orange outline) / active states. Not available with the other choices = muted border + text and a subtle diagonal strike drawn by a pseudo-element on the chip (no text line-through); such chips stay clickable so the shopper can still switch to a combination that exists. Checked on product 1 (long names): phone chips 147x30, 273x30, 343x45 (2 lines, no overflow); desktop 188x35, 355x35, 532x35; RTL 375 identical.

## Video popup (decision #114)
Verified on product 190 (desktop, Playwright): "Play Video" thumb -> video slide active (native video, contain, poster); scroll away -> popup bottom inline-end (24 / 24, 140 wide) continuing at the gallery time (~3.0 s); click -> back to the gallery (scrollY 0), video continues; close -> hidden for the page view. Mouse-drag swipe off the video slide pauses it. With `prefers-reduced-motion: reduce`: popup shows without animation (0.08 s transition), restore scrolls instantly. Also seen on the phone run (popup above the add-to-cart bar).

## Data / texts
`ProductPageData::$alsoViewed` / `$recommended` (Elora variant 1 only: best sellers, then top rated / newest, never repeating the product or the first rail). Texts ar/en: `product.play_video`, `elora_pages.product.also_viewed|recommended|zoom`. Shared partials only gained optional props (`gallery.video-thumb label`, `product-trust images`) and per-card classes.

## Remaining differences
- Figma's content block is asymmetric (x=103..1390); we centre the same 1287 width.
- Not rendered: the "Day Salary" ribbon (marketing art), the `200 g` weight next to the rating, review country flags, a chevron on the delivery card, tabby / tamara images (not in the project; other gateways show text chips).
- The free-shipping bar / promo strip need a free-shipping threshold, not set for the dev country, so they were not visible in the screenshots.
- At 1280-1439 the 6th-card layout shows cards narrower than 210 (183 at 1280) because the container is narrower than Figma's 1360.
