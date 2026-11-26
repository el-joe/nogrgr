/**
 * Header mini cart drawer (Ecommet 1:1662) and the "Item added to cart" dialog (1:4267).
 *  - the desktop header cart entry ([data-mini-cart-open]) opens the drawer, which loads
 *    `storefront.cart.mini` (skeleton first); the cart page itself keeps the link;
 *  - the drawer slides in from the inline-end edge, closes on Esc / scrim / ✕ and can be
 *    dragged (mouse or touch) back toward its edge; focus is trapped and restored;
 *  - `sf:cart-updated` (catalog/cart.js after add to cart) shows the dialog with the
 *    added product and quantity.
 */
import { bindCartLines, enhanceLines } from './lines';
import { fragmentLoader, setCartCount } from './api';
import { isRtl, prefersReducedMotion, token } from '../core/motion/utils';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

function trap(dialog, event) {
    if (event.key !== 'Tab') {
        return;
    }

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

function show(dialog) {
    dialog.__opener = document.activeElement;
    dialog.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => {
        dialog.dataset.open = '';
        dialog.querySelector('[data-autofocus], button, a[href]')?.focus({ preventScroll: true });
    }));
}

function hide(dialog) {
    if (dialog.hidden) {
        return;
    }

    delete dialog.dataset.open;
    const done = () => {
        dialog.hidden = true;
        document.documentElement.style.overflow = '';
    };
    prefersReducedMotion() ? done() : setTimeout(done, token('--motion-base', dialog, 280));
    dialog.__opener?.focus?.({ preventScroll: true });
}

function bindDrag(drawer) {
    const panel = drawer.querySelector('[data-mini-cart-panel]');
    let start = null;

    panel.addEventListener('pointerdown', (event) => {
        if (event.target.closest('a, button, input, select, label') || event.button !== 0) {
            return;
        }

        start = { x: event.clientX, y: event.clientY, time: performance.now(), dx: 0, axis: null, id: event.pointerId };
    });

    panel.addEventListener('pointermove', (event) => {
        if (!start) {
            return;
        }

        const dx = event.clientX - start.x;
        start.axis ??= Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(event.clientY - start.y) ? 'x' : Math.abs(event.clientY - start.y) > 8 ? 'y' : null;

        if (start.axis !== 'x') {
            if (start.axis === 'y') {
                start = null;
            }
            return;
        }

        panel.setPointerCapture?.(start.id);
        /* Toward the edge it came from: right in LTR, left in RTL. */
        start.dx = isRtl(drawer) ? Math.min(0, dx) : Math.max(0, dx);
        panel.style.transition = 'none';
        panel.style.transform = `translateX(${start.dx}px)`;
    });

    const end = () => {
        if (!start) {
            return;
        }

        const distance = Math.abs(start.dx);
        const velocity = distance / Math.max(1, performance.now() - start.time);
        panel.style.transition = '';
        panel.style.transform = '';

        if (distance > panel.offsetWidth * 0.3 || (velocity > 0.5 && distance > 30)) {
            hide(drawer);
        }

        start = null;
    };

    panel.addEventListener('pointerup', end);
    panel.addEventListener('pointercancel', end);
}

export function initMiniCart() {
    const drawer = document.querySelector('[data-mini-cart]');
    const added = document.querySelector('[data-cart-added]');

    if (!drawer || drawer.__ready) {
        return;
    }

    drawer.__ready = true;
    /* Out of the (transformed) sticky header so `fixed` is relative to the viewport. */
    document.body.append(drawer);
    if (added) {
        document.body.append(added);
    }

    const body = drawer.querySelector('[data-mini-cart-body]');
    const load = fragmentLoader(drawer.dataset.url);
    const refresh = async () => {
        try {
            const data = await load();
            body.innerHTML = data.html ?? body.innerHTML;
            setCartCount(data.items_count);
            enhanceLines(body);
        } catch {
            /* the http client already toasted the failure */
        }
    };

    bindCartLines(body, refresh);
    bindDrag(drawer);

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-mini-cart-open]');

        if (opener && !document.querySelector('[data-cart-page]') && window.matchMedia('(min-width: 1024px)').matches) {
            event.preventDefault();
            show(drawer);
            refresh();
            return;
        }

        if (event.target.closest('[data-mini-cart-close]')) {
            hide(drawer);
        }

        if (event.target.closest('[data-cart-added-close]')) {
            hide(added);
        }
    });

    document.addEventListener('keydown', (event) => {
        const open = [added, drawer].find((dialog) => dialog && !dialog.hidden);

        if (!open) {
            return;
        }

        if (event.key === 'Escape') {
            hide(open);
        } else {
            trap(open, event);
        }
    });

    /* Added to cart → the confirmation dialog (product name + quantity from the cart). */
    document.addEventListener('sf:cart-updated', (event) => {
        const { cart, product } = event.detail ?? {};

        if (!added || !cart) {
            return;
        }

        const line = (cart.lines ?? []).find((item) => item.store_product_id === product?.store_product_id && (!product.variant_id || item.variant_id === product.variant_id));
        const quantity = product?.quantity ?? 1;
        const text = added.querySelector('[data-cart-added-text]');
        const qty = added.querySelector('[data-cart-added-qty]');
        text.textContent = text.dataset.template.replace('__COUNT__', '1');
        added.querySelector('[data-cart-added-name]').textContent = line?.name ?? '';
        qty.textContent = qty.dataset.template.replace('__QTY__', String(quantity));
        setTimeout(() => show(added), prefersReducedMotion() ? 0 : token('--motion-slow', document.documentElement, 520));
    });
}
