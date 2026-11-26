/**
 * Souqify inner-page behaviour (listing, product, cart, checkout, account). Everything
 * else (cart API, fragments, reviews, variants, sheets, rails, gallery, swipe-remove)
 * comes from the shared catalog / commerce / core modules through their data-* hooks.
 *  - listing: the desktop filter sidebar applies on change ([data-sq-autosubmit]);
 *    long facet lists filter as you type ([data-sq-facet-search]);
 *  - cart lines: − / + steppers ([data-sq-step]) drive the shared quantity input;
 *  - product: the − n + stepper drives the [data-qty] input read by the cart script; the
 *    "Add to cart – price" label follows the live variant price; share button (Web Share
 *    API, clipboard fallback); delivery chart columns get their height ([data-height]);
 *  - server fragments: data-* → CSS vars again after every swap (meters, bars);
 *  - order details: the "Tracking" tab opens the Souqify tracking page;
 *  - listing: filter changes / pagination mark the grid busy (skeleton fade);
 *  - mini cart drawer (souqify/pages/mini-cart.js).
 */
import { applyDataVars } from '../catalog/ui';
import { notify } from '../../core/notify';
import { initSouqifyMiniCart } from './pages/mini-cart';

function bindAutosubmit(root) {
    root.querySelectorAll('form[data-sq-autosubmit]:not([data-ready])').forEach((form) => {
        form.dataset.ready = '';
        let timer = null;

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-sq-facet-search]')) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => {
                form.setAttribute('aria-busy', 'true');
                document.getElementById('sq-listing-grid')?.setAttribute('aria-busy', 'true');
                form.requestSubmit();
            }, 250);
        });
    });
}

function bindFacetSearch(root) {
    root.querySelectorAll('[data-sq-facet-search]:not([data-ready])').forEach((input) => {
        input.dataset.ready = '';
        const facet = input.closest('[data-sq-facet]');
        const empty = facet?.querySelector('[data-sq-facet-empty]');

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let shown = 0;

            facet.querySelectorAll('[data-sq-facet-value]').forEach((row) => {
                const match = !term || row.dataset.sqFacetValue.includes(term);
                row.hidden = !match;
                shown += match ? 1 : 0;
            });

            if (empty) {
                empty.hidden = shown > 0;
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
}

function bindSteppers() {
    if (document.__sqSteppers) {
        return;
    }

    document.__sqSteppers = true;

    /* Cart lines: − / + set the shared [data-cart-qty] input and fire its change. */
    document.addEventListener('click', (event) => {
        const step = event.target.closest('[data-sq-step]');

        if (step) {
            const input = step.closest('[data-sq-stepper]')?.querySelector('[data-cart-qty]');

            if (!input) {
                return;
            }

            const next = Math.min(Number(input.max) || 99, Math.max(1, Number(input.value) + Number(step.dataset.sqStep)));

            if (next !== Number(input.value)) {
                input.value = String(next);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return;
        }

        /* Product page: − / + set the [data-qty] input the cart script reads. */
        const mirror = event.target.closest('[data-sq-qty-mirror] [data-step]');

        if (mirror) {
            const box = mirror.closest('[data-sq-qty-mirror]');
            const input = box.querySelector('input');
            const max = Number(box.dataset.max) || 10;
            const next = Math.min(max, Math.max(1, Number(input.value) + Number(mirror.dataset.step)));
            input.value = String(next);
            box.querySelector('[data-step="-1"]').disabled = next <= 1;
            box.querySelector('[data-step="1"]').disabled = next >= max;
        }
    });
}

function bindShare() {
    if (document.__sqShare) {
        return;
    }

    document.__sqShare = true;
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-sq-share]');

        if (!button) {
            return;
        }

        const url = button.dataset.shareUrl || window.location.href;

        if (navigator.share) {
            try {
                await navigator.share({ title: button.dataset.shareTitle, url });
            } catch {
                /* dismissed */
            }

            return;
        }

        try {
            await navigator.clipboard.writeText(url);
            notify.success(button.dataset.shareDone);
        } catch {
            window.prompt(button.getAttribute('aria-label') ?? '', url);
        }
    });
}

/** The add-to-cart label repeats the live price (product.js updates [data-price] per variant). */
function bindPriceMirror(root) {
    const page = root.querySelector('[data-product-page]');
    const mirror = page?.querySelector('[data-sq-price-mirror]');
    const price = page?.querySelector('[data-price]');
    const button = page?.querySelector('[data-add-to-cart]');

    if (!mirror || !price || mirror.__sq) {
        return;
    }

    mirror.__sq = true;
    const sync = () => {
        mirror.textContent = ` – ${price.textContent.trim()}`;
        mirror.hidden = Boolean(button?.disabled);
    };
    new MutationObserver(sync).observe(price, { childList: true, characterData: true, subtree: true });

    if (button) {
        new MutationObserver(sync).observe(button, { attributes: true, attributeFilter: ['disabled'] });
    }
}

function applyHeights(root) {
    root.querySelectorAll('[data-height]').forEach((bar) => bar.style.setProperty('--h', `${Number(bar.dataset.height) || 0}%`));
}

/** Fragments swapped in by the cart / checkout scripts → re-apply data-* CSS variables. */
function watchFragments(root) {
    root.querySelectorAll('[data-cart-region], [data-cart-summary-region], [data-checkout-summary-region]').forEach((region) => {
        if (region.__sqWatch) {
            return;
        }

        region.__sqWatch = true;
        new MutationObserver(() => {
            applyDataVars(region);
            applyHeights(region);
        }).observe(region, { childList: true });
    });
}

/** Order details: "Tracking" navigates to the Souqify tracking page instead of switching in place. */
function bindOrderTabs() {
    if (document.__sqOrderTabs) {
        return;
    }

    document.__sqOrderTabs = true;
    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-order-tab="tracking"]');

        if (tab && !event.metaKey && !event.ctrlKey) {
            event.stopPropagation();
        }
    }, true);
}

export function initSouqifyPages(root = document) {
    bindAutosubmit(root);
    bindFacetSearch(root);
    bindSteppers();
    bindShare();
    watchFragments(root);
    bindOrderTabs();
    bindPriceMirror(root);
    applyHeights(root);
    initSouqifyMiniCart();
}
