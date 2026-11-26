/**
 * Storefront cart / checkout / payment bundle (sf-checkout), shared by every family.
 * The mini cart + "added to cart" dialog run on every page; page modules load on demand.
 */
import { initMiniCart } from './mini-cart';

export function initCommerce(root = document) {
    initMiniCart();

    const cart = root.querySelector('[data-cart-page]');
    const checkout = root.querySelector('[data-checkout-page]');
    const pay = root.querySelector('[data-payment-page]');
    const redirect = root.querySelector('[data-payment-redirect]');

    if (cart) {
        import('./cart-page').then(({ initCartPage }) => initCartPage(cart));
    }

    if (checkout) {
        import('./checkout').then(({ initCheckout }) => initCheckout(checkout));
    }

    if (pay) {
        import('./checkout').then(({ initPayPage }) => initPayPage(pay));
    }

    if (redirect) {
        import('./payment').then(({ initPaymentRedirect }) => initPaymentRedirect(redirect));
    }
}
