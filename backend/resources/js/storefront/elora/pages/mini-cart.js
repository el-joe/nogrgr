/**
 * Elora mini cart (Storefront additions 137:112132 side panel / m 137:112323 bottom sheet,
 * x-sf::elora.inner.cart.drawer):
 *  - the header cart entry ([data-mini-cart-open]) opens it on desktop (the cart page keeps
 *    the link); after "Add to cart" (`sf:cart-updated`) it opens on every screen size with
 *    the "Added to your cart" note;
 *  - content comes from `storefront.cart.mini` (skeleton first) and its lines use the
 *    shared cart-line behaviour (swipe / trash remove with undo);
 *  - closes on Esc / scrim / ✕, or by dragging toward its edge (desktop) / down (phones);
 *    focus is trapped and restored; reduced motion skips the slide.
 */
import { bindCartLines, enhanceLines } from '../../commerce/lines';
import { fragmentLoader, setCartCount } from '../../commerce/api';
import { applyDataVars } from '../../catalog/ui';
import { isRtl, prefersReducedMotion, token } from '../../core/motion/utils';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
const desktop = () => window.matchMedia('(min-width: 1024px)').matches;

function trap(dialog, event) {
    const items = [...dialog.querySelectorAll(FOCUSABLE)].filter((node) => node.offsetParent !== null);
    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

function bindDrag(drawer, close) {
    const panel = drawer.querySelector('[data-elo-mini-panel]');
    let start = null;

    panel.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.target.closest('a, button, input, select, label, [data-swipe-remove]')) {
            return;
        }

        start = { x: event.clientX, y: event.clientY, time: performance.now(), delta: 0, id: event.pointerId, axis: desktop() ? 'x' : 'y', locked: false };
    });

    panel.addEventListener('pointermove', (event) => {
        if (!start) {
            return;
        }

        const dx = event.clientX - start.x;
        const dy = event.clientY - start.y;

        if (!start.locked) {
            const along = start.axis === 'x' ? Math.abs(dx) : Math.abs(dy);
            const across = start.axis === 'x' ? Math.abs(dy) : Math.abs(dx);

            if (along < 8 && across < 8) {
                return;
            }

            /* Phones: only a downward drag that starts at the top of the scroll area closes. */
            const body = drawer.querySelector('[data-elo-mini-body]');
            if (across > along || (start.axis === 'y' && (dy < 0 || body.scrollTop > 0))) {
                start = null;
                return;
            }

            start.locked = true;
            panel.setPointerCapture?.(start.id);
        }

        start.delta = start.axis === 'x' ? (isRtl(drawer) ? Math.min(0, dx) : Math.max(0, dx)) : Math.max(0, dy);
        panel.style.transition = 'none';
        panel.style.transform = start.axis === 'x' ? `translateX(${start.delta}px)` : `translateY(${start.delta}px)`;
    });

    const end = () => {
        if (!start) {
            return;
        }

        const size = start.axis === 'x' ? panel.offsetWidth : panel.offsetHeight;
        const distance = Math.abs(start.delta);
        const velocity = distance / Math.max(1, performance.now() - start.time);
        panel.style.transition = '';
        panel.style.transform = '';

        if (distance > size * 0.3 || (velocity > 0.5 && distance > 30)) {
            close();
        }

        start = null;
    };

    panel.addEventListener('pointerup', end);
    panel.addEventListener('pointercancel', end);
}

export function initEloraMiniCart() {
    const drawer = document.querySelector('[data-elo-mini-cart]');

    if (!drawer || drawer.__ready) {
        return;
    }

    drawer.__ready = true;
    /* Out of the (transformed) sticky header so `fixed` is relative to the viewport. */
    document.body.append(drawer);

    const body = drawer.querySelector('[data-elo-mini-body]');
    const load = fragmentLoader(drawer.dataset.url);

    const refresh = async () => {
        try {
            const data = await load();
            body.innerHTML = data.html ?? body.innerHTML;
            setCartCount(data.items_count);
            enhanceLines(body);
            applyDataVars(body);
        } catch {
            /* the http client already toasted the failure */
        }
    };

    const open = (added = false) => {
        drawer.toggleAttribute('data-added', added);

        if (!drawer.hidden) {
            refresh();
            return;
        }

        drawer.__opener = document.activeElement;
        drawer.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => {
            drawer.dataset.open = '';
            drawer.querySelector('[data-elo-mini-close]:not(.absolute)')?.focus({ preventScroll: true });
        }));
        refresh();
    };

    const close = () => {
        if (drawer.hidden) {
            return;
        }

        delete drawer.dataset.open;
        const done = () => {
            drawer.hidden = true;
            document.documentElement.style.overflow = '';
        };
        prefersReducedMotion() ? done() : setTimeout(done, token('--motion-base', drawer, 320));
        drawer.__opener?.focus?.({ preventScroll: true });
    };

    bindCartLines(body, refresh);
    bindDrag(drawer, close);

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-mini-cart-open]') && !document.querySelector('[data-cart-page]') && desktop()) {
            event.preventDefault();
            open(false);
            return;
        }

        if (event.target.closest('[data-elo-mini-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        /* While the "Remove selected" confirmation is open, Escape and Tab belong to it. */
        if (drawer.hidden || document.querySelector('.swal2-container')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'Tab') {
            trap(drawer, event);
        }
    });

    /* Added to cart (product page, cards, reorder) → open with the "Added" note, after the fly-to-cart arc. */
    document.addEventListener('sf:cart-updated', (event) => {
        if (!event.detail?.cart || document.querySelector('[data-cart-page]')) {
            return;
        }

        setTimeout(() => open(true), prefersReducedMotion() ? 0 : token('--motion-slow', document.documentElement, 600));
    });
}
