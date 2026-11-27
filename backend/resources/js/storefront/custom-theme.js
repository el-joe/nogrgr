/**
 * Runtime of uploaded Twig storefront themes (decision #121), printed by `{{ scripts() }}`.
 * Themes ship no store logic: they mark up elements with data-nt-* attributes and this
 * script wires them to the Store API (docs/themes/README.md § Runtime):
 *
 *   [data-nt-add-to-cart] data-product-id [data-variant-id] [data-quantity]
 *       (or inside form[data-nt-product-form]: fields name=variant_id / name=quantity)
 *   [data-nt-cart-count]                       updated after every cart change
 *   input[data-nt-cart-qty] data-line-id       change → new quantity, page reload
 *   [data-nt-cart-remove] data-line-id         remove line, page reload
 *   form[data-nt-coupon-form] (name=code)      apply coupon, page reload
 *   [data-nt-coupon-remove]                    remove coupon, page reload
 *   [data-nt-favorite] data-product-id         wishlist toggle, aria-pressed (guests → sign in)
 *   select[data-nt-variant-select]             updates [data-nt-price] / [data-nt-compare]
 *       from <script type="application/json" data-nt-variants> (product.variants)
 *   form[data-nt-newsletter-form] (name=email) newsletter sign-up
 *   [data-nt-status]                           aria-live status line (optional data-msg-* texts)
 *
 * Store tracking pixels: when the store has pixels on, scripts() prints the #sf-tracking
 * island + consent notice and this runtime loads core/tracking.js (consent-gated, #91).
 */
import { http } from '../core/http';
import { cartApi } from './commerce/api';
import { context } from './catalog/context';
import '../../css/storefront/custom-theme-consent.css';

const status = () => document.querySelector('[data-nt-status]');
let statusTimer = null;

function say(message, { error = false, fallback = null } = {}) {
    const node = status();
    const text = message || (fallback ? node?.dataset[fallback] : '') || '';

    if (!node || !text) {
        return;
    }

    window.clearTimeout(statusTimer);
    node.textContent = text;
    node.classList.toggle('is-error', error);
    node.classList.add('is-visible');
    statusTimer = window.setTimeout(() => node.classList.remove('is-visible'), error ? 6000 : 3500);
}

const firstError = (error) => {
    const errors = Object.values(error?.payload?.errors ?? {}).flat();

    return errors[0] || error?.payload?.message || '';
};

function fail(error) {
    if (error?.name === 'AbortError') {
        return;
    }

    say(firstError(error), { error: true, fallback: 'msgError' });
}

function setCount(count) {
    if (typeof count !== 'number') {
        return;
    }

    document.querySelectorAll('[data-nt-cart-count]').forEach((counter) => {
        counter.textContent = String(count);
        counter.hidden = count === 0;
    });
}

const busy = async (element, work) => {
    if (element?.getAttribute('aria-busy') === 'true') {
        return;
    }

    element?.setAttribute('aria-busy', 'true');

    try {
        await work();
    } finally {
        element?.removeAttribute('aria-busy');
    }
};

const toInt = (value, fallback = null) => {
    const number = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(number) && number > 0 ? number : fallback;
};

const reload = () => window.location.reload();

const loginUrl = () => context().routes?.login || document.documentElement.dataset.loginUrl || '/account/login';

/* ---------- Cart ---------- */

async function addToCart(button, form = null) {
    const productId = toInt(button.dataset.productId);

    if (!productId) {
        return;
    }

    const variantId = toInt(form?.elements.namedItem('variant_id')?.value ?? button.dataset.variantId);
    const quantity = toInt(form?.elements.namedItem('quantity')?.value ?? button.dataset.quantity, 1);

    await busy(button, async () => {
        try {
            const response = await cartApi.add(productId, quantity, variantId);
            setCount(response?.data?.items_count);
            say(response?.message, { fallback: 'msgAdded' });
        } catch (error) {
            fail(error);
        }
    });
}

async function changeQuantity(input) {
    const lineId = toInt(input.dataset.lineId);
    const quantity = toInt(input.value);

    if (!lineId || !quantity) {
        return;
    }

    input.disabled = true;

    try {
        await cartApi.updateQuantity(lineId, quantity);
        reload();
    } catch (error) {
        input.disabled = false;
        fail(error);
    }
}

async function removeLine(button) {
    const lineId = toInt(button.dataset.lineId);

    if (!lineId) {
        return;
    }

    await busy(button, async () => {
        try {
            await cartApi.remove(lineId);
            reload();
        } catch (error) {
            fail(error);
        }
    });
}

async function applyCoupon(form) {
    const code = String(form.elements.namedItem('code')?.value ?? '').trim();

    if (!code) {
        return;
    }

    await busy(form.querySelector('[type="submit"]'), async () => {
        try {
            await cartApi.applyCoupon(code);
            reload();
        } catch (error) {
            fail(error);
        }
    });
}

async function removeCoupon(button) {
    await busy(button, async () => {
        try {
            await cartApi.removeCoupon();
            reload();
        } catch (error) {
            fail(error);
        }
    });
}

/* ---------- Wishlist ---------- */

async function toggleFavorite(button) {
    const productId = toInt(button.dataset.productId);
    const url = context().routes?.favorite_toggle || '/favorites/toggle';

    if (!productId) {
        return;
    }

    await busy(button, async () => {
        try {
            const response = await http.post(url, { store_product_id: productId }, { silent: true, toast: false });
            const favorited = Boolean(response?.data?.favorited);

            document.querySelectorAll(`[data-nt-favorite][data-product-id="${productId}"]`).forEach((item) => item.setAttribute('aria-pressed', String(favorited)));
            say(response?.message, { fallback: favorited ? 'msgFavorited' : 'msgUnfavorited' });
        } catch (error) {
            if (error?.status === 401) {
                window.location.assign(error.payload?.redirect || loginUrl());

                return;
            }

            fail(error);
        }
    });
}

/* ---------- Newsletter ---------- */

async function subscribe(form) {
    await busy(form.querySelector('[type="submit"]'), async () => {
        try {
            const response = await http.post(form.action, new FormData(form), { silent: true, toast: false });
            form.reset();
            say(response?.message, { fallback: 'msgSubscribed' });
        } catch (error) {
            fail(error);
        }
    });
}

/* ---------- Variants ---------- */

/**
 * The island is printed by Twig with HTML autoescaping, so it may arrive entity-encoded
 * (&quot;); decode once before parsing.
 */
function readVariants(form) {
    const island = form.querySelector('script[data-nt-variants]') ?? document.querySelector('script[data-nt-variants]');
    const raw = island?.textContent?.trim() ?? '';

    if (!raw) {
        return [];
    }

    for (const text of [raw, new DOMParser().parseFromString(raw, 'text/html').documentElement.textContent ?? '']) {
        try {
            const value = JSON.parse(text);

            return Array.isArray(value) ? value : [];
        } catch {
            /* try the decoded text */
        }
    }

    return [];
}

function selectVariant(select) {
    const form = select.closest('form[data-nt-product-form]') ?? document;
    const variants = (select._ntVariants ??= readVariants(form));
    const variant = variants.find((item) => String(item?.id) === select.value);

    if (!variant) {
        return;
    }

    const scope = select.closest('form[data-nt-product-form]')?.parentElement ?? document;

    scope.querySelectorAll('[data-nt-price]').forEach((node) => {
        node.textContent = variant.price ?? '';
    });
    scope.querySelectorAll('[data-nt-compare]').forEach((node) => {
        node.textContent = variant.compare_price ?? '';
        node.hidden = !variant.compare_price;
    });

    form.querySelectorAll?.('[data-nt-add-to-cart]').forEach((button) => {
        button.disabled = variant.in_stock === false;
    });

    const quantity = form.elements?.namedItem?.('quantity');

    if (quantity && typeof variant.stock === 'number' && variant.stock > 0) {
        quantity.max = String(variant.stock);
    }

    document.dispatchEvent(new CustomEvent('nt:variant-changed', { detail: { variant } }));
}

/* ---------- Wiring (event delegation, works for markup added later) ---------- */

function init() {
    // Store tracking pixels, consent first (decisions #91 + #121): same loader as the built-in themes.
    if (document.getElementById('sf-tracking')) {
        import('./core/tracking.js').then(({ initTracking }) => initTracking());
    }

    if (status()) {
        /* Theme shows its own status line: keep the platform toasts out of the theme's layout. */
        const region = document.getElementById('ui-toasts') ?? Object.assign(document.createElement('div'), { id: 'ui-toasts' });
        region.hidden = true;
        document.body.appendChild(region);
    }

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;

        const add = target?.closest('[data-nt-add-to-cart]');
        if (add && !(add.type === 'submit' && add.closest('form[data-nt-product-form]'))) {
            event.preventDefault();
            addToCart(add, add.closest('form[data-nt-product-form]'));

            return;
        }

        const remove = target?.closest('[data-nt-cart-remove]');
        if (remove) {
            event.preventDefault();
            removeLine(remove);

            return;
        }

        const couponRemove = target?.closest('[data-nt-coupon-remove]');
        if (couponRemove) {
            event.preventDefault();
            removeCoupon(couponRemove);

            return;
        }

        const favorite = target?.closest('[data-nt-favorite]');
        if (favorite) {
            event.preventDefault();
            toggleFavorite(favorite);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;

        if (form?.matches('[data-nt-product-form]')) {
            event.preventDefault();
            const button = event.submitter?.matches('[data-nt-add-to-cart]') ? event.submitter : form.querySelector('[data-nt-add-to-cart]');

            if (button) {
                addToCart(button, form);
            }
        } else if (form?.matches('[data-nt-coupon-form]')) {
            event.preventDefault();
            applyCoupon(form);
        } else if (form?.matches('[data-nt-newsletter-form]')) {
            event.preventDefault();
            subscribe(form);
        }
    });

    document.addEventListener('change', (event) => {
        const target = event.target instanceof Element ? event.target : null;

        if (target?.matches('input[data-nt-cart-qty]')) {
            changeQuantity(target);
        } else if (target?.matches('select[data-nt-variant-select]')) {
            selectVariant(target);
        }
    });

    document.querySelectorAll('select[data-nt-variant-select]').forEach((select) => {
        if (select.value) {
            selectVariant(select);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
