# Elora card add-to-cart (variants -> modal, no variants -> direct)

## What changed
- `resources/js/storefront/catalog/variant-picker.js` (moved from `ecommet/`): shared picker module. It finds `[data-variant-modal]` and reads its class map from `data-variant-skin` (Ecommet defaults when absent). Ecommet and Elora entries both call `initVariantPicker()`.
- `components/storefront/variant-picker.blade.php`: new `theme` prop. `ecommet` renders `#eco-variant-modal` (unchanged look). `elora` renders `#sf-variant-modal` with the `v1-vp-*` skin. Texts reuse the existing `catalog.variant.*` and `product.*` keys (ar/en already present).
- `css/storefront/elora-picker.css` (imported by `storefront-elora.css`, so it is part of the existing Vite entry): Elora tokens, radii and orange primary, bottom sheet on phones via `x-sf::modal`, plus the disabled card cart button.
- Cards: `elora/v1/card.blade.php` and `elora/product-card.blade.php` (variants 2+). A variant product in stock gets `<a data-variant-picker>`, which is also the no-JS fallback to the PDP. An out-of-stock product gets a disabled button. A product without variants keeps `[data-add-to-cart]`.
- `footer/elora.blade.php`: renders the picker once for every Elora variant, so every page that uses the layout has it.
- `elora/index.js`: `initVariantPicker()`.

## Paths
- No variants: `[data-add-to-cart]` -> motion morph and fly-to-cart -> `catalog/cart.js` POST `cart/items` -> `sf:cart-updated`. The header and tab bar `[data-cart-count]` update, and the Elora mini-cart drawer opens with the "Added" note.
- Variants: `[data-variant-picker]` -> sheet -> product loaded from the Store API -> all options must be chosen (Add stays disabled and shows "choose") -> qty limited to stock and 10 -> the same add-to-cart handler -> the sheet closes.
- All handlers are delegated on `document`, so cards injected by facets or AJAX work.
- Prices and stock come from the API. Nothing is computed in JS.

## Verified with Playwright (vendor.nogrgr.loc, Elora v1)
| Page | Result |
|---|---|
| / | modal present; variant card opens the modal; direct card (product 53) POST 201, no modal; count 0 -> 1 |
| /categories | no product cards |
| /category/jewelry | variant modal OK; still OK after a facet AJAX re-render |
| /offers, /offers?flash=1 | variant modal OK |
| /best-selling, /new-in | variant modal OK |
| /search?q=ring | variant modal OK |
| /products/53 and /products/1 (PDP rails) | rail cards open the modal; PDP add button POST 201 |
| /cart (recommendations) | variant modal OK |
| /zzz-404 (suggestions) | variant modal OK |
| /account/wishlist | redirects to login (not logged in); uses the same card component |

- Widths 375, 768 and 1440: the modal opens, a tile is picked, and Add enables and posts (201, count increments, modal closes).
- RTL (dir flipped on the page) with a dark colour scheme at 375: layout is mirrored correctly. The storefront has no dark theme, so it renders light.
- The dev DB has almost only variant products. Product 53 is the only one without variants.
- Out-of-stock: no such product in the dev DB, so the disabled state was not exercised visually. The code path is the disabled button, the same as the Ecommet card.

Screenshots (`/var/www/nogrgr/.playwright-mcp/`): `elora-card-cart-modal-{375,768,1440}.png`, `elora-card-cart-modal-picked-{375,768,1440}.png`, `elora-card-cart-added-1440.png`, `elora-card-cart-modal-375-rtl-dark.png`, `elora-card-cart-added-375-rtl-dark.png`.

## Deviations
- Success feedback is the existing Elora mini-cart drawer ("Added") plus the count bump and the button morph. There is no separate toast, matching Ecommet, which uses its popup. Errors (stock exceeded, 422/401) go through the shared http client toast.
- The real Arabic locale was not toggled. RTL was checked by flipping `dir` on the page.
- Order-success page, account suggestion pages and wishlist were not browsed (login or order needed). They render the same card components under the same layout and footer.
