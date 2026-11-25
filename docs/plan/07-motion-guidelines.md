# Storefront Motion & Swipe Guidelines (client 2026-11-25)

The client loves animation and swiping. Every theme home page (13) and the shared inner pages must feel premium and alive **without changing the Figma design** — layout, colors, typography and spacing stay exactly as designed; motion is layered on top.

## 1. Principles
- **Purposeful:** motion guides attention (hero → offers → products), confirms actions (add to cart, favorite), and makes content feel tactile. Nothing moves just to move.
- **Fast & smooth:** animate `transform` and `opacity` only (GPU), 60 fps on a mid-range phone; no layout shift (CLS = 0 from animations); never block interaction (INP < 200 ms).
- **Consistent tokens** (CSS custom properties per family, tunable per theme): `--motion-fast 150ms`, `--motion-base 280ms`, `--motion-slow 600ms`, `--ease-out cubic-bezier(.22,1,.36,1)`, `--ease-in-out cubic-bezier(.65,0,.35,1)`, `--ease-spring` (via JS spring for drag release), stagger `60ms`.
- **Accessible:** honour `prefers-reduced-motion` (reveal = instant, carousels no autoplay, no parallax); every swipe has a button/keyboard equivalent; autoplay pauses on hover, focus, touch and when off-screen; WCAG 2.2.2 pause control on any auto-moving strip > 5 s.
- **RTL-aware:** swipe and slide directions mirror in Arabic.

## 2. Swipe (phone + desktop) — every horizontal rail
Built on **Swiper** (already a project dependency) + small custom modules:
- Touch swipe on phones and **mouse drag on desktop** (`simulateTouch`, `grabCursor`), trackpad / Shift+wheel horizontal scrolling (`mousewheel: { forceToAxis: true }`), keyboard arrows when focused, visible prev/next buttons where Figma shows them.
- Momentum / free-mode with snap for product rails; resistance + rubber-band at the ends; `touchAngle` tuned so vertical page scroll is never hijacked.
- Progress or pagination indicator where Figma shows one; lazy-load slide images; slides are links that don't fire on drag.
- Reusable modules: `rail` (product/category rails), `hero` (fade/slide + Ken-Burns zoom on image, autoplay with progress bar), `spotlight` (center/left progressive scaling — `old-system-docs/home-pages-custom-sliders.md`, used **only** where Figma shows the custom card-fan/coverflow slider, e.g. Elora 1:30232), `marquee` (Featured/trust strip 1:29865 — infinite, pauses on hover/touch, draggable), `tabs-swipe` (tabbed product sections swipeable between tabs on mobile), `gallery` (product page: swipe images, pinch/double-tap zoom, thumbnails sync).
- Mobile extras: swipe-to-dismiss bottom sheets (address, filters, sort, cart mini), swipe-to-remove on cart lines (with undo toast), pull-to-close image viewer.

## 3. Scroll & entrance animation
- Section reveal via IntersectionObserver (`data-reveal="fade-up|fade|zoom|slide-start"`, `data-reveal-stagger` for card grids) — each element animates once, 20–40 px travel, staggered children.
- Subtle parallax on hero/banner imagery (≤ 8% translate, disabled on reduced motion and low-end devices).
- Counters (countdown flip for flash sale digits, number count-up for stats) only when in view.
- Sticky header: shrink + shadow on scroll, hide on scroll down / show on scroll up (mobile).

## 4. Micro-interactions
- Product card: image cross-fade to 2nd image on hover (desktop), lift + shadow, quick-add button slide-in; favourite heart burst; badge shimmer for "flash"/"new" (subtle, once).
- Add to cart: button morph (spinner → check), **fly-to-cart** thumbnail arc to the cart icon, cart count bump.
- Buttons/links: press scale (0.97), ripple-free; focus rings animate in.
- Skeleton shimmer for loading rails; image fade-in on load (blur-up from tiny placeholder).
- Page transitions between storefront pages with the **View Transitions API** where supported (shared-element morph product card → product page image), graceful fallback.
- Seasonal: snow layer only for the Souqify Christmas skin (post-launch).

## 5. Ownership
- **UI/UX:** for each of the 13 home pages and shared inner pages, add a **"Motion spec" frame on our own Figma page "Storefront motion"** (never on client pages): which sections reveal how, which rails swipe, autoplay timings, hover states, mobile gestures; prototype key interactions (smart-animate) where useful. Keep the original designs untouched.
- **Frontend:** implement as shared modules in `resources/js/storefront/core/motion/` + per-family tokens; sections opt in via `data-*` attributes; no per-theme copy-paste. Bundle budget: motion JS ≤ 25 KB gz (Swiper loaded per page only where rails exist).
- **QA / SEO:** test swipe on real touch emulation + mouse drag + keyboard; Lighthouse performance ≥ 90 mobile, CLS 0, no long tasks from animation; reduced-motion pass; RTL swipe direction.
