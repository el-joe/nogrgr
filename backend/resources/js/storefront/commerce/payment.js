/**
 * Payment section of the old project (spec 08 §1–§2) for the checkout, the pay page and
 * the redirect page:
 *  - nothing pre-selected; selecting Stripe shows the inline card panel (Stripe.js Card
 *    Element through the shared core/payments/stripe-card.js — loaded only then,
 *    decision #78 b); PayPal is a hosted redirect;
 *  - startPayment(): POST /store-api/v1/payments/{order}/start → PayPal follows the
 *    provider URL; Stripe confirms the PaymentIntent with the card (3-D Secure inside
 *    Stripe's own dialog) and opens `checkout/payment/stripe/{order}?status=success`,
 *    where the server reads the intent and marks the order paid (no webhook). A declined
 *    card shows its message in the panel; the next Pay retries the same intent.
 */
import { storeApi } from './api';
import { toast } from '../../core/notify';
import { createStripeCard } from '../../core/payments/stripe-card';

const fill = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`__${key}__`, encodeURIComponent(value)), template);

export function returnUrl(config, gateway, order, status, reference = '') {
    const url = fill(config.return, { GATEWAY: gateway, ORDER: order });

    return `${url}?${new URLSearchParams({ status, reference })}`;
}

/** Rejection of startPayment() when the card was declined (message already in the panel). */
export class CardDeclined extends Error {}

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
    const card = panel ? createStripeCard(panel, { key: null, errorText: messages.card_error ?? panel.dataset.cardErrorText }) : null;
    const selected = () => section.querySelector('[data-gateway-radio]:checked');
    const isInline = (radio) => radio?.dataset.inline === '1' && Boolean(radio.dataset.publishableKey);

    const onSelect = () => {
        const radio = selected();
        error.hidden = true;

        if (!panel) {
            return;
        }

        panel.hidden = !isInline(radio);
        card.showError('');

        if (isInline(radio)) {
            card.mount(radio.dataset.publishableKey).catch(() => {});
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
        card,
        /** The started inline payment kept for a retry after a decline: {order, secret, reference}. */
        pending: null,
        isInline: () => isInline(selected()),
        /** Shows "Please select a payment method." / card problems; false when not ready. */
        validate() {
            const radio = selected();

            if (!radio) {
                error.textContent = messages.select_method ?? '';
                error.hidden = false;
                section.querySelector('[data-gateway-radio]')?.focus();

                return false;
            }

            if (isInline(radio) && !card.validate()) {
                card.explain().then((message) => toast('error', messages.failed_title ?? '', message));

                return false;
            }

            return true;
        },
    };
}

/**
 * Starts the payment of a placed order with the selected gateway: a redirect gateway
 * follows the provider's hosted page; Stripe confirms the card in-page (the intent of a
 * declined attempt is reused for the retry) and opens the return URL.
 * @returns {Promise<void>} navigates away on success; rejects with CardDeclined or a reason
 */
export async function startPayment({ config, order, gateway, section = null }) {
    const inline = section?.isInline() && section.card;
    let pending = inline && section.pending?.order === order && section.pending.gateway === gateway ? section.pending : null;

    if (!pending) {
        const url = fill(config.start, { ORDER: order });
        const response = await storeApi.post(url, { gateway }, {}, { silent: true });
        const data = response?.data ?? {};

        if (data.redirect_url) {
            window.location.assign(data.redirect_url);
            return;
        }

        if (inline && data.client_secret) {
            pending = { order, gateway, secret: data.client_secret, reference: data.payment?.reference ?? '' };
        }
    }

    if (pending) {
        const result = await section.card.confirm(pending.secret);

        if (result.ok) {
            section.pending = null;
            window.location.assign(returnUrl(config, gateway, order, 'success', pending.reference));
            return;
        }

        section.pending = result.retry ? pending : null;
        toast('error', config.messages?.failed_title ?? '', result.message ?? '');
        throw new CardDeclined(result.message ?? '');
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
