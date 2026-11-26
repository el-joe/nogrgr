/**
 * Add to cart from cards and the product page: the motion module (core/motion/cart.js)
 * runs the button morph + fly-to-cart and fires `cart:add`; this sends the Store API
 * request (POST /store-api/v1/cart/items) and hands the promise back so the morph waits.
 * Every success dispatches `sf:cart-updated` (detail = Store API cart data) on document,
 * which the mini-cart / added-to-cart popup listen to; counters follow items_count.
 */
import { http } from '../../core/http';
import { context } from './context';

const setCounts = (count) => {
    document.querySelectorAll('[data-cart-count]').forEach((counter) => {
        counter.textContent = String(count);
        counter.hidden = false;
    });
};

async function add(button) {
    const url = context().routes.cart_items;

    if (!url) {
        throw new Error('cart unavailable');
    }

    const scope = button.closest('[data-product-page]');
    const quantity = Number(scope?.querySelector('[data-qty]')?.value || 1);
    const payload = { store_product_id: Number(button.dataset.productId), quantity };

    if (button.dataset.variantId) {
        payload.variant_id = Number(button.dataset.variantId);
    }

    const response = await http.post(url, payload, { toast: false });
    const cart = response?.data ?? {};

    if (typeof cart.items_count === 'number') {
        setCounts(cart.items_count);
    }

    document.dispatchEvent(new CustomEvent('sf:cart-updated', { detail: { cart, product: payload, message: response?.message, source: button } }));

    const added = scope?.querySelector('[data-added-label]');

    if (added) {
        added.hidden = false;
    }

    return cart;
}

let bound = false;

export function initCart() {
    if (bound) {
        return;
    }

    bound = true;
    document.addEventListener('cart:add', (event) => {
        if (event.target.closest('[data-add-to-cart]')?.dataset.productId) {
            event.detail.done(add(event.detail.button));
        }
    });

    /* Header counters: one light cart read once the page is idle. */
    const url = context().routes.cart_items?.replace(/\/items$/, '');
    const load = () => url && http.get(url, null, { silent: true, toast: false, followRedirect: false })
        .then((response) => typeof response?.data?.items_count === 'number' && setCounts(response.data.items_count))
        .catch(() => {});
    ('requestIdleCallback' in window ? window.requestIdleCallback : (fn) => setTimeout(fn, 1500))(load);
}
