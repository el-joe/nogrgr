/**
 * Elora inner-page behaviour (listing, product, cart, checkout, account). Everything
 * else (cart API, fragments, reviews, variants, sheets, rails, gallery, swipe-remove)
 * comes from the shared catalog / commerce / core modules through their data-* hooks.
 *  - listing: the desktop filter sidebar applies on change ([data-elo-autosubmit]);
 *    long facet lists filter as you type ([data-elo-facet-search]); the v1 listing
 *    (.elp-*) has its own module, elora/pages/listing.js;
 *  - cart lines: − / + steppers ([data-elo-step]) drive the shared quantity input;
 *  - product: the phone quantity stepper mirrors the desktop select ([data-qty]);
 *    share button (Web Share API, clipboard fallback);
 *  - server fragments: data-* → CSS vars again after every swap (meters, bars);
 *  - order details: the "Tracking" tab opens the Elora tracking page;
 *  - mini cart drawer (elora/pages/mini-cart.js).
 */
import { applyDataVars } from '../catalog/ui';
import { notify } from '../../core/notify';
import { initEloraListing } from './pages/listing';
import { initEloraMiniCart } from './pages/mini-cart';

function bindAutosubmit(root) {
    root.querySelectorAll('form[data-elo-autosubmit]:not([data-ready])').forEach((form) => {
        form.dataset.ready = '';
        let timer = null;

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-elo-facet-search], [data-elp-search]')) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => {
                form.setAttribute('aria-busy', 'true');
                document.getElementById('elo-listing-grid')?.setAttribute('aria-busy', 'true');
                form.requestSubmit();
            }, 250);
        });
    });
}

function bindFacetSearch(root) {
    root.querySelectorAll('[data-elo-facet-search]:not([data-ready])').forEach((input) => {
        input.dataset.ready = '';
        const facet = input.closest('[data-elo-facet]');
        const empty = facet?.querySelector('[data-elo-facet-empty]');

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let shown = 0;

            facet.querySelectorAll('[data-elo-facet-value]').forEach((row) => {
                const match = !term || row.dataset.eloFacetValue.includes(term);
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
    if (document.__eloSteppers) {
        return;
    }

    document.__eloSteppers = true;

    /* Cart lines: − / + set the shared [data-cart-qty] input and fire its change. */
    document.addEventListener('click', (event) => {
        const step = event.target.closest('[data-elo-step]');

        if (step) {
            const input = step.closest('[data-elo-stepper]')?.querySelector('[data-cart-qty]');

            if (!input) {
                return;
            }

            const next = Math.min(Number(input.max) || 99, Math.max(1, Number(input.value) + Number(step.dataset.eloStep)));

            if (next !== Number(input.value)) {
                input.value = String(next);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return;
        }

        /* Product page (phones): the stepper mirrors the hidden desktop select read by the cart script. */
        const mirror = event.target.closest('[data-elo-qty-mirror] [data-step]');

        if (mirror) {
            const box = mirror.closest('[data-elo-qty-mirror]');
            const input = box.querySelector('input');
            const max = Number(box.dataset.max) || 10;
            const next = Math.min(max, Math.max(1, Number(input.value) + Number(mirror.dataset.step)));
            input.value = String(next);
            box.querySelector('[data-step="-1"]').disabled = next <= 1;
            box.querySelector('[data-step="1"]').disabled = next >= max;
            const source = document.querySelector('[data-elo-qty-source]');

            if (source) {
                source.value = String(next);
            }
        }
    });
}

function bindShare() {
    if (document.__eloShare) {
        return;
    }

    document.__eloShare = true;
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-elo-share]');

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

/** Fragments swapped in by the cart / checkout scripts → re-apply data-* CSS variables. */
function watchFragments(root) {
    root.querySelectorAll('[data-cart-region], [data-cart-summary-region], [data-checkout-summary-region]').forEach((region) => {
        if (region.__eloWatch) {
            return;
        }

        region.__eloWatch = true;
        new MutationObserver(() => applyDataVars(region)).observe(region, { childList: true });
    });
}

/** Order details: "Tracking" navigates to the Elora tracking page instead of switching in place. */
function bindOrderTabs() {
    if (document.__eloOrderTabs) {
        return;
    }

    document.__eloOrderTabs = true;
    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-order-tab="tracking"]');

        if (tab && !event.metaKey && !event.ctrlKey) {
            event.stopPropagation();
        }
    }, true);
}

export function initEloraPages(root = document) {
    bindAutosubmit(root);
    bindFacetSearch(root);
    initEloraListing(root);
    bindSteppers();
    bindShare();
    watchFragments(root);
    bindOrderTabs();
    initEloraMiniCart();
}
