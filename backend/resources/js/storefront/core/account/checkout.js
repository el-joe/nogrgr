/**
 * Checkout address step (x-sf::checkout.address-step): keeps the hidden
 * shipping_address_id / billing_address_id / billing_same_as_shipping inputs in sync with
 * the radio cards, shows the billing list when "same as shipping" is unticked, renders
 * the shipping info box, and disables the pay button with the reason underneath
 * (no address → shipping problem → no payment gateway).
 *
 * Events on the root (bubbling):
 *   dispatches  checkout:address-change  {shipping, billing, billingSame}
 *   listens     checkout:shipping        {state: ok|no_country|unavailable|not_sold, label, products}
 *               checkout:gateway         {available}
 */
import { initAddressBook } from './address-book';

const readJson = (node) => {
    try {
        return JSON.parse(node?.textContent || '{}');
    } catch {
        return {};
    }
};

export function initCheckoutStep(root) {
    if (root.__checkout) {
        return;
    }

    root.__checkout = true;
    const config = readJson(root.querySelector('[data-checkout-config]'));
    const messages = config.messages ?? {};
    const book = initAddressBook(root);
    const shippingId = root.querySelector('[data-shipping-id]');
    const billingId = root.querySelector('[data-billing-id]');
    const sameValue = root.querySelector('[data-billing-same-value]');
    const same = root.querySelector('[data-billing-same]');
    const billingGroup = root.querySelector('[data-address-group="billing"]');
    const pay = root.querySelector('[data-pay-button]');
    const reason = root.querySelector('[data-pay-reason]');
    const ok = root.querySelector('[data-shipping-ok]');
    const error = root.querySelector('[data-shipping-error]');
    let shipping = config.shipping ?? { state: 'ok' };
    let gateway = config.gatewayAvailable !== false;

    const renderShipping = () => {
        const state = shipping.state ?? 'ok';

        if (ok) {
            ok.hidden = state !== 'ok' || !shipping.label;
            const label = ok.querySelector('[data-shipping-label]');
            if (label && shipping.label) {
                label.textContent = shipping.label;
            }
        }

        if (error) {
            error.hidden = state === 'ok';
            const text = error.querySelector('[data-shipping-error-text]');
            if (text && state !== 'ok') {
                text.textContent = (messages[state] ?? messages.unavailable ?? '').replace('__PRODUCTS__', (shipping.products ?? []).join(', '));
            }
        }
    };

    const update = () => {
        const selected = book.selection();
        const billingSame = same?.checked !== false;
        const shippingValue = selected.shipping ?? '';
        const billingValue = billingSame ? shippingValue : (selected.billing ?? '');

        shippingId.value = shippingValue;
        billingId.value = billingValue;
        sameValue.value = billingSame ? '1' : '0';
        if (billingGroup) {
            billingGroup.hidden = billingSame;
        }

        let why = '';
        if (!shippingValue || !billingValue) {
            why = messages.reason_address;
        } else if ((shipping.state ?? 'ok') !== 'ok') {
            why = messages.reason_shipping;
        } else if (!gateway) {
            why = messages.reason_gateway;
        }

        if (pay) {
            pay.disabled = Boolean(why);
            pay.setAttribute('aria-disabled', String(Boolean(why)));
        }
        if (reason) {
            reason.hidden = !why;
            reason.querySelector('span').textContent = why ?? '';
        }

        return { shipping: shippingValue || null, billing: billingValue || null, billingSame };
    };

    const announce = () => {
        root.dispatchEvent(new CustomEvent('checkout:address-change', { bubbles: true, detail: update() }));
    };

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-address-radio], [data-billing-same]')) {
            announce();
        }
    });
    root.addEventListener('address-book:change', announce);
    root.addEventListener('checkout:shipping', (event) => {
        shipping = { ...shipping, ...(event.detail ?? {}) };
        renderShipping();
        update();
    });
    root.addEventListener('checkout:gateway', (event) => {
        gateway = Boolean(event.detail?.available);
        update();
    });

    renderShipping();
    update();
}

export function initCheckoutSteps(root = document) {
    root.querySelectorAll('[data-checkout-address]').forEach(initCheckoutStep);
}
