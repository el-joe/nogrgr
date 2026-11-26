/**
 * Store API calls of the theme cart / checkout (/store-api/v1, session + CSRF). Every
 * call sends the storefront display currency (`X-Currency`, display-only conversion) and
 * keeps the header cart counters in step with the returned cart.
 */
import { http } from '../../core/http';
import { context } from '../catalog/context';

const base = () => (context().routes.cart_items ?? '/store-api/v1/cart/items').replace(/\/cart\/items$/, '');

const headers = (extra = {}) => ({ 'X-Currency': context().currency ?? '', ...extra });

export const readJson = (node) => {
    try {
        return JSON.parse(node?.textContent || '{}');
    } catch {
        return {};
    }
};

export const money = (value) => value?.display?.formatted ?? value?.formatted ?? '';

export function setCartCount(count) {
    if (typeof count !== 'number') {
        return;
    }

    document.querySelectorAll('[data-cart-count]').forEach((counter) => {
        const changed = counter.textContent !== String(count);
        counter.textContent = String(count);
        counter.hidden = false;

        if (changed) {
            counter.classList.remove('mo-bump');
            void counter.offsetWidth;
            counter.classList.add('mo-bump');
        }
    });
}

const cartCall = async (method, path, data, options = {}) => {
    const response = await http.request(method, `${base()}${path}`, { data, headers: headers(), toast: false, ...options });
    setCartCount(response?.data?.items_count);
    document.dispatchEvent(new CustomEvent('sf:cart-changed', { detail: { cart: response?.data ?? null } }));

    return response;
};

export const cartApi = {
    updateQuantity: (id, quantity) => cartCall('PATCH', `/cart/items/${id}`, { quantity }),
    remove: (id) => cartCall('DELETE', `/cart/items/${id}`),
    add: (storeProductId, quantity, variantId = null) => cartCall('POST', '/cart/items', { store_product_id: storeProductId, quantity, ...(variantId ? { variant_id: variantId } : {}) }),
    applyCoupon: (code) => cartCall('POST', '/cart/coupon', { code }, { silent: true }),
    removeCoupon: () => cartCall('DELETE', '/cart/coupon'),
};

export const storeApi = {
    post: (url, data, extraHeaders = {}, options = {}) => http.request('POST', url, { data, headers: headers(extraHeaders), toast: false, ...options }),
};

/**
 * GET a server-rendered fragment (JSON {data}); stale responses are dropped.
 */
export function fragmentLoader(url) {
    let controller = null;

    return async (params = {}) => {
        controller?.abort();
        controller = new AbortController();
        const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')).toString();
        const response = await http.get(query ? `${url}${url.includes('?') ? '&' : '?'}${query}` : url, null, { signal: controller.signal, toast: false, headers: headers() });

        return response?.data ?? {};
    };
}
