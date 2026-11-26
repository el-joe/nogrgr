/**
 * Payment section of the old project (spec 08 §1–§3) for the checkout, the pay page and
 * the redirect page:
 *  - nothing pre-selected; selecting an inline gateway shows the card panel (Stripe Card
 *    Element; Authorize.Net / 2Checkout card number · MM / YY · CVC) — the gateway SDK
 *    is loaded only then;
 *  - startPayment(): POST /store-api/v1/payments/{order}/start → redirect gateways follow
 *    the provider URL, Stripe confirms the PaymentIntent (3-D Secure inside Stripe's own
 *    dialog) and lands on the return URL `checkout/payment/{gateway}/{order}?status=…`.
 */
import { storeApi } from './api';
import { toast } from '../../core/notify';

const STRIPE_SDK = 'https://js.stripe.com/v3/';
const MANUAL = ['authorize_net', '2checkout'];
let stripeLoader = null;

function loadStripe() {
    stripeLoader ??= new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve(window.Stripe);
            return;
        }

        const script = document.createElement('script');
        script.src = STRIPE_SDK;
        script.async = true;
        script.onload = () => (window.Stripe ? resolve(window.Stripe) : reject(new Error('stripe')));
        script.onerror = () => {
            stripeLoader = null;
            reject(new Error('stripe'));
        };
        document.head.append(script);
    });

    return stripeLoader;
}

const fill = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`__${key}__`, encodeURIComponent(value)), template);

export function returnUrl(config, gateway, order, status, reference = '') {
    const url = fill(config.return, { GATEWAY: gateway, ORDER: order });

    return `${url}?${new URLSearchParams({ status, reference })}`;
}

function formatCardInputs(panel) {
    panel.querySelector('[data-card-number]')?.addEventListener('input', (event) => {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 19);
        event.target.value = digits.replace(/(.{4})/g, '$1 ').trim();
    });
    panel.querySelector('[data-card-expiry]')?.addEventListener('input', (event) => {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 4);
        event.target.value = digits.length > 2 ? `${digits.slice(0, 2)} / ${digits.slice(2)}` : digits;
    });
    panel.querySelector('[data-card-cvc]')?.addEventListener('input', (event) => {
        event.target.value = event.target.value.replace(/\D/g, '').slice(0, 4);
    });
}

/**
 * Binds one payment section; returns its API.
 */
export function initPaymentSection(root, messages = {}) {
    const section = root.querySelector('[data-payment-section]');

    if (!section) {
        return null;
    }

    const error = section.querySelector('[data-payment-error]');
    const panel = section.querySelector('[data-inline-card]');
    const stripeBox = panel?.querySelector('[data-card-stripe]');
    const manualBox = panel?.querySelector('[data-card-manual]');
    const state = { stripe: null, card: null, complete: false, key: null };

    if (panel) {
        formatCardInputs(panel);
    }

    const cardError = (text) => {
        panel?.querySelectorAll('[data-card-error]').forEach((node) => {
            node.textContent = text ?? '';
            node.hidden = !text;
        });
    };

    const mountStripe = async (radio) => {
        const key = radio.dataset.publishableKey;

        if (!key || (state.card && state.key === key)) {
            return;
        }

        try {
            const Stripe = await loadStripe();
            state.stripe = Stripe(key, { locale: document.documentElement.lang || 'auto' });
            state.card?.destroy();
            state.card = state.stripe.elements().create('card', {
                hidePostalCode: true,
                style: { base: { fontSize: '14px', color: '#333', '::placeholder': { color: '#aaa' } } },
            });
            state.card.mount(stripeBox.querySelector('[data-stripe-element]'));
            state.card.on('change', (event) => {
                state.complete = event.complete;
                cardError(event.error?.message ?? '');
            });
            state.key = key;
        } catch {
            cardError(messages.card_error);
        }
    };

    const selected = () => section.querySelector('[data-gateway-radio]:checked');

    const onSelect = () => {
        const radio = selected();
        error.hidden = true;

        if (!radio || !panel) {
            return;
        }

        const inline = radio.dataset.inline === '1';
        panel.hidden = !inline;
        stripeBox.hidden = radio.value !== 'stripe';
        manualBox.hidden = !MANUAL.includes(radio.value);
        cardError('');

        if (inline && radio.value === 'stripe') {
            mountStripe(radio);
        }
    };

    section.addEventListener('change', (event) => {
        if (event.target.matches('[data-gateway-radio]')) {
            onSelect();
        }
    });

    const preselect = root.querySelector('[data-preselect-gateway]')?.value;
    const preset = preselect ? section.querySelector(`[data-gateway-radio][value="${CSS.escape(preselect)}"]`) : null;

    if (preset) {
        preset.checked = true;
        onSelect();
    }

    return {
        selected,
        /** Shows "Please select a payment method." / card problems; false when not ready. */
        validate() {
            const radio = selected();

            if (!radio) {
                error.textContent = messages.select_method ?? '';
                error.hidden = false;
                section.querySelector('[data-gateway-radio]')?.focus();
                return false;
            }

            if (radio.dataset.inline === '1' && radio.value === 'stripe' && !state.complete) {
                cardError(messages.card_error);
                return false;
            }

            if (MANUAL.includes(radio.value)) {
                const filled = ['[data-card-number]', '[data-card-expiry]', '[data-card-cvc]'].every((selector) => manualBox.querySelector(selector)?.value.trim());

                if (!filled) {
                    cardError(messages.card_error);
                    return false;
                }
            }

            return true;
        },
        cardError,
        state,
    };
}

/**
 * Starts the payment of a placed order with the selected gateway.
 * @returns {Promise<void>} navigates away on success; rejects with a reason otherwise
 */
export async function startPayment({ config, order, gateway, section }) {
    const url = fill(config.start, { ORDER: order });
    const response = await storeApi.post(url, { gateway }, {}, { silent: true });
    const data = response?.data ?? {};

    if (data.redirect_url) {
        window.location.assign(data.redirect_url);
        return;
    }

    if (data.client_secret && section?.state.stripe && section.state.card) {
        const result = await section.state.stripe.confirmCardPayment(data.client_secret, { payment_method: { card: section.state.card } });

        if (result.error) {
            section.cardError(result.error.message ?? config.messages?.card_error);
            window.location.assign(returnUrl(config, gateway, order, 'failed', data.payment?.reference ?? ''));
            return;
        }

        window.location.assign(returnUrl(config, gateway, order, 'success', data.payment?.reference ?? ''));
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
