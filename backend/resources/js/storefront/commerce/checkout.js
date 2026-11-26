/**
 * Checkout page (SF-ECO-023). The address step (core/account/checkout.js, sf-account)
 * owns the radio cards, the address modal, billing-same-as-shipping and the pay-button
 * reasons; this module:
 *  - re-prices on every address change through `storefront.checkout.fragment` (summary
 *    for the shipping country, shipping info box state, block reason, "Pay {total}");
 *  - applies / removes coupons (lines.js → Store API → fragment);
 *  - places the order (POST /store-api/v1/checkout/place with an Idempotency-Key that
 *    stays the same while the request body does) and starts the payment (payment.js),
 *    with the full-screen #checkout-overlay and the "Processing..." button meanwhile.
 */
import { bindCartLines } from './lines';
import { fragmentLoader, readJson, storeApi } from './api';
import { CardDeclined, initPaymentSection, paymentFailed, startPayment } from './payment';

const EXTRA_BLOCKS = ['unavailable_items', 'coupon_invalid', 'empty_cart'];

const newKey = () => (window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`).replace(/[^A-Za-z0-9_\-:.]/g, '');

function busyButton(button, label) {
    if (!button) {
        return () => {};
    }

    const html = button.innerHTML;
    const disabled = button.disabled;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '';
    const spinner = document.createElement('span');
    spinner.className = 'size-4 animate-spin rounded-full border-2 border-current border-e-transparent motion-reduce:animate-none';
    spinner.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = label;
    const wrap = document.createElement('span');
    wrap.className = 'flex items-center justify-center gap-2';
    wrap.append(spinner, text);
    button.append(wrap);

    return () => {
        button.innerHTML = html;
        button.disabled = disabled;
        button.removeAttribute('aria-busy');
    };
}

export function initCheckout(page) {
    if (page.__checkout) {
        return;
    }

    page.__checkout = true;
    const config = readJson(page.querySelector('[data-checkout-page-config]'));
    const messages = config.messages ?? {};
    const form = page.querySelector('[data-checkout-form]');
    const step = page.querySelector('[data-checkout-address]');
    const pay = page.querySelector('[data-pay-button]');
    const reason = page.querySelector('[data-pay-reason]');
    const overlay = page.querySelector('[data-checkout-overlay]');
    const payment = initPaymentSection(page, messages);
    const load = fragmentLoader(config.fragment);
    let block = config.block;
    let selection = { shipping: step?.querySelector('[data-shipping-id]')?.value || null, billing: step?.querySelector('[data-billing-id]')?.value || null, billingSame: true };
    let key = { body: null, value: null };

    /* Blocks the address step does not know about (items, coupon, empty cart). */
    const applyExtraBlock = () => {
        if (!pay || !EXTRA_BLOCKS.includes(block) || pay.disabled) {
            return;
        }

        pay.disabled = true;
        pay.setAttribute('aria-disabled', 'true');

        if (reason) {
            reason.hidden = false;
            reason.querySelector('span').textContent = messages[block] ?? '';
        }
    };

    const refresh = async () => {
        page.querySelectorAll('[data-checkout-summary-region]').forEach((region) => region.setAttribute('aria-busy', 'true'));

        try {
            const data = await load({
                shipping_address_id: selection.shipping,
                billing_address_id: selection.billingSame ? selection.shipping : selection.billing,
                billing_same_as_shipping: selection.billingSame ? 1 : 0,
            });

            if (data.empty) {
                window.location.assign(config.cart);
                return;
            }

            page.querySelectorAll('[data-checkout-summary-region]').forEach((region) => (region.innerHTML = data.summary ?? region.innerHTML));
            block = data.block;

            if (pay && !pay.hasAttribute('aria-busy')) {
                pay.textContent = messages.pay.replace('__TOTAL__', data.pay_total ?? '');
            }

            /* USD is the only payment currency: keep "You will be charged $X USD." current. */
            const charge = page.querySelector('[data-usd-charge]');

            if (charge) {
                charge.hidden = !data.usd_charge;
                charge.querySelector('[data-usd-charge-text]').textContent = data.usd_charge ?? '';
            }

            step?.dispatchEvent(new CustomEvent('checkout:shipping', { detail: data.shipping }));
            step?.dispatchEvent(new CustomEvent('checkout:gateway', { detail: { available: data.gateways > 0 } }));
            applyExtraBlock();
        } catch {
            /* aborted by a newer request, or toasted by the http client */
        } finally {
            page.querySelectorAll('[data-checkout-summary-region]').forEach((region) => region.setAttribute('aria-busy', 'false'));
        }
    };

    bindCartLines(page, refresh);
    applyExtraBlock();

    let timer = null;
    page.addEventListener('checkout:address-change', (event) => {
        selection = { ...selection, ...(event.detail ?? {}) };
        clearTimeout(timer);
        timer = setTimeout(refresh, 150);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!pay || pay.disabled || !payment?.validate()) {
            return;
        }

        const gateway = payment.selected().value;
        const body = {
            gateway,
            shipping_address_id: Number(selection.shipping) || null,
            billing_address_id: Number(selection.billingSame ? selection.shipping : selection.billing) || null,
            billing_same_as_shipping: selection.billingSame,
        };
        const signature = JSON.stringify(body);

        if (key.body !== signature) {
            key = { body: signature, value: `chk-${newKey()}` };
        }

        const restore = busyButton(pay, messages.processing);
        overlay.hidden = false;

        let order = null;

        try {
            const placed = await storeApi.post(config.place, body, { 'Idempotency-Key': key.value });
            order = placed?.data?.payment_order;
        } catch {
            /* the http client toasted the reason (address, stock, shipping, gateway…) */
            overlay.hidden = true;
            restore();
            refresh();
            return;
        }

        try {
            await startPayment({ config, order, gateway, section: payment });
        } catch (failure) {
            if (failure instanceof CardDeclined) {
                /* Declined card: the message is in the card panel; Pay again retries the same intent. */
                overlay.hidden = true;
                restore();
                return;
            }

            /* The order exists: blocked gateway / provider error → toast, then its pay page. */
            paymentFailed(config, failure);
            setTimeout(() => window.location.assign(config.pay.replace('__ORDER__', order)), 1800);
        }
    });
}

/**
 * Pay page of a placed order (retry / complete payment).
 */
export function initPayPage(page) {
    if (page.__pay) {
        return;
    }

    page.__pay = true;
    const config = readJson(page.querySelector('[data-checkout-page-config]'));
    const form = page.querySelector('[data-pay-form]');
    const pay = page.querySelector('[data-pay-button]');
    const overlay = page.querySelector('[data-checkout-overlay]');
    const payment = initPaymentSection(page, config.messages ?? {});

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!pay || pay.disabled || !payment?.validate()) {
            return;
        }

        const gateway = payment.selected().value;
        const restore = busyButton(pay, config.messages?.processing ?? '');
        overlay.hidden = false;

        try {
            await startPayment({ config, order: config.order, gateway, section: payment });
        } catch (failure) {
            overlay.hidden = true;
            restore();

            if (!(failure instanceof CardDeclined)) {
                paymentFailed(config, failure);
            }
        }
    });
}
