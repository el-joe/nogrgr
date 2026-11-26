/**
 * Payment section of the old project (spec 08 §1) for the checkout, the pay page and
 * the redirect page:
 *  - nothing pre-selected; every gateway is a hosted redirect (Stripe Checkout, PayPal):
 *    no gateway script or card field is ever loaded on the store (decision #78 b);
 *  - startPayment(): POST /store-api/v1/payments/{order}/start → follow the provider URL;
 *    the provider sends the payer back to `checkout/payment/{gateway}/{order}?status=…`.
 */
import { storeApi } from './api';
import { toast } from '../../core/notify';

const fill = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`__${key}__`, encodeURIComponent(value)), template);

/**
 * Binds one payment section; returns its API.
 */
export function initPaymentSection(root, messages = {}) {
    const section = root.querySelector('[data-payment-section]');

    if (!section) {
        return null;
    }

    const error = section.querySelector('[data-payment-error]');
    const selected = () => section.querySelector('[data-gateway-radio]:checked');

    section.addEventListener('change', (event) => {
        if (event.target.matches('[data-gateway-radio]')) {
            error.hidden = true;
        }
    });

    const preselect = root.querySelector('[data-preselect-gateway]')?.value;
    const preset = preselect ? section.querySelector(`[data-gateway-radio][value="${CSS.escape(preselect)}"]`) : null;

    if (preset) {
        preset.checked = true;
    }

    return {
        selected,
        /** Shows "Please select a payment method."; false when nothing is chosen. */
        validate() {
            if (selected()) {
                return true;
            }

            error.textContent = messages.select_method ?? '';
            error.hidden = false;
            section.querySelector('[data-gateway-radio]')?.focus();

            return false;
        },
    };
}

/**
 * Starts the payment of a placed order with the selected gateway and follows the
 * provider's hosted page (Stripe Checkout / PayPal); without a URL, the pay page.
 * @returns {Promise<void>} navigates away on success; rejects with a reason otherwise
 */
export async function startPayment({ config, order, gateway }) {
    const url = fill(config.start, { ORDER: order });
    const response = await storeApi.post(url, { gateway }, {}, { silent: true });
    const target = response?.data?.redirect_url;

    if (target) {
        window.location.assign(target);
        return;
    }

    window.location.assign(fill(config.pay, { ORDER: order }) + `?gateway=${encodeURIComponent(gateway)}`);
}

/**
 * Failure toasts of the payment start (Figma 114:109173): gateway blocked / failed.
 */
export function paymentFailed(config, failure) {
    const reason = failure?.payload?.data?.reason ?? failure?.payload?.reason;
    const blocked = reason === 'gateway_unavailable' || reason === 'no_gateway';
    const messages = config.messages ?? {};
    toast('error', blocked ? messages.gateway_blocked_title : messages.failed_title, blocked ? messages.gateway_blocked : (failure?.payload?.message || messages.failed), {
        timeout: 8000,
    });
}

/**
 * Redirect page (114:108887): start the payment and follow the provider URL.
 */
export async function initPaymentRedirect(root) {
    const start = root.dataset.start;
    const gateway = root.dataset.gateway;
    const link = root.querySelector('[data-redirect-continue]');

    try {
        const response = await storeApi.post(start, { gateway }, { 'X-Currency': root.dataset.currency ?? '' }, { silent: true });
        const target = response?.data?.redirect_url;

        if (target) {
            link.href = target;
            setTimeout(() => window.location.assign(target), 400);
            return;
        }

        window.location.assign(root.dataset.failed);
    } catch {
        root.querySelector('[data-redirect-spinner]')?.setAttribute('hidden', '');
        window.location.assign(root.dataset.failed);
    }
}
