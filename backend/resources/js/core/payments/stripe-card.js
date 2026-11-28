/**
 * Inline Stripe card for every payment point (decision #78 b, like the old project):
 * storefront checkout / pay page, central registration, vendor subscription, settlements,
 * language purchase and request payments.
 *
 * This module is the ONLY place that loads Stripe.js (v3, the STRIPE_JS constant below), and only
 * on demand — when a page shows the card because Stripe is the chosen gateway
 * (tests/Unit/Architecture/NoThirdPartyScriptsTest.php enforces both).
 *
 *  - createStripeCard(panel): mounts a Card Element into `[data-stripe-element]` of a
 *    `[data-stripe-card]` panel (publishable key / locale from its data attributes) and
 *    confirms a PaymentIntent client secret with it (3-D Secure in Stripe's own dialog);
 *  - bindStripeCardForms(root): every `[data-stripe-card]` inside a <form> (the Blade
 *    component <x-ui::stripe-card>) shows while the form's gateway field is "stripe"; the
 *    submit then posts the form as JSON → {data.inline: {client_secret, return_url}} →
 *    confirm → open return_url, where the server verifies the PaymentIntent and settles.
 *    A declined card shows its message in the panel and the same intent is retried.
 */
import { http } from '../http.js';

const STRIPE_JS = 'https://js.stripe.com/v3/';
let loader = null;

/** Loads Stripe.js once; rejects (and allows a retry) when it cannot be loaded. */
export function loadStripe() {
    loader ??= new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve(window.Stripe);
            return;
        }

        const script = document.createElement('script');
        script.src = STRIPE_JS;
        script.async = true;
        script.onload = () => (window.Stripe ? resolve(window.Stripe) : reject(new Error('stripe')));
        script.onerror = () => {
            loader = null;
            script.remove();
            reject(new Error('stripe'));
        };
        document.head.append(script);
    });

    return loader;
}

const STRIPE_LOCALES = ['ar', 'en'];

/**
 * Card controller of one `[data-stripe-card]` panel.
 */
export function createStripeCard(panel, { key = panel?.dataset.publishableKey, errorText = panel?.dataset.cardErrorText ?? '' } = {}) {
    const state = { stripe: null, card: null, key: null, complete: false, mounting: null, lastError: '' };
    const box = panel.querySelector('[data-stripe-element]');
    const error = panel.querySelector('[data-stripe-error]');

    const showError = (text) => {
        if (error) {
            error.textContent = text ?? '';
            error.hidden = !text;
        }
    };

    const mount = (publishableKey = key) => {
        if (!publishableKey || !box) {
            return Promise.reject(new Error('stripe'));
        }

        if (state.card && state.key === publishableKey) {
            return Promise.resolve(state);
        }

        state.mounting ??= loadStripe()
            .then((Stripe) => {
                const locale = panel.dataset.locale ?? document.documentElement.lang;
                const styles = getComputedStyle(box);
                state.stripe = Stripe(publishableKey, { locale: STRIPE_LOCALES.includes(locale) ? locale : 'auto' });
                state.card?.destroy();
                state.card = state.stripe.elements().create('card', {
                    hidePostalCode: true,
                    style: {
                        base: { fontSize: '14px', color: styles.color, fontFamily: styles.fontFamily, '::placeholder': { color: '#9ca3af' } },
                        invalid: { color: '#dc2626' },
                    },
                });
                state.card.mount(box);
                state.card.on('change', (event) => {
                    state.complete = event.complete;
                    state.lastError = event.error?.message ?? '';
                    showError(state.lastError);
                });
                state.key = publishableKey;

                return state;
            })
            .catch((failure) => {
                showError(errorText);
                throw failure;
            })
            .finally(() => {
                state.mounting = null;
            });

        return state.mounting;
    };

    return {
        state,
        mount,
        showError,
        /** true when the card fields are complete; otherwise shows the card error. */
        validate() {
            if (state.card && state.complete) {
                return true;
            }

            showError(state.lastError || errorText);
            state.card?.focus();

            return false;
        },
        /**
         * Stripe's own (localised) reason why the card is not ready, shown inline. When no change
         * event reported one (empty / half filled card) Stripe is asked to validate the element:
         * an incomplete card never creates a payment method.
         * @returns {Promise<string>}
         */
        async explain() {
            let message = state.lastError;

            if (!message && state.stripe && state.card && !state.complete) {
                const result = await state.stripe.createPaymentMethod({ type: 'card', card: state.card }).catch(() => null);
                message = result?.error?.message ?? '';
            }

            message ||= errorText;
            showError(message);

            return message;
        },
        /**
         * Confirms the PaymentIntent with the mounted card.
         * @returns {Promise<{ok: boolean, retry: boolean, message?: string}>}
         */
        async confirm(clientSecret) {
            if (!state.stripe || !state.card) {
                showError(errorText);
                return { ok: false, retry: false };
            }

            const result = await state.stripe.confirmCardPayment(clientSecret, { payment_method: { card: state.card } });

            if (result.error) {
                const message = result.error.message || errorText;
                showError(message);
                const status = result.error.payment_intent?.status;

                /* Declined: the intent waits for another card (requires_source on old Stripe API versions). */
                return { ok: false, retry: !status || ['requires_payment_method', 'requires_source'].includes(status), message };
            }

            showError('');

            return { ok: true, retry: false };
        },
    };
}

function gatewayValue(form, field) {
    const input = form.elements.namedItem(field);

    if (!input) {
        return '';
    }

    return input instanceof RadioNodeList || input instanceof HTMLSelectElement || input instanceof HTMLInputElement ? input.value : '';
}

function busy(form) {
    const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type])'), ...(form.id ? document.querySelectorAll(`button[form="${CSS.escape(form.id)}"]`) : [])];
    const saved = buttons.map((button) => [button, button.disabled]);
    buttons.forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });

    return () => {
        saved.forEach(([button, disabled]) => {
            button.disabled = disabled;
            button.removeAttribute('aria-busy');
        });
    };
}

function bindForm(panel) {
    const form = panel.closest('form');

    if (!form || form.__stripeCard) {
        return;
    }

    form.__stripeCard = true;
    const field = panel.dataset.gatewayField || 'gateway';
    const value = panel.dataset.gatewayValue || 'stripe';
    const card = createStripeCard(panel);
    let pending = null;

    /*
     * Stripe.js is only loaded once the card is really on screen: a card inside a closed
     * modal (display: none) never intersects, so pages that merely contain the form
     * (invoice list, subscription modals) load no Stripe script until the modal opens.
     */
    let observer = null;
    const mountWhenShown = () => {
        if (card.state.card || observer) {
            return;
        }

        observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                observer.disconnect();
                observer = null;
                card.mount().catch(() => {});
            }
        });
        observer.observe(panel);
    };

    const sync = () => {
        const chosen = gatewayValue(form, field) === value;
        panel.hidden = !chosen;
        /* "You will be redirected…" copy belongs to hosted gateways only. */
        form.querySelectorAll('[data-hosted-hint]').forEach((hint) => {
            hint.hidden = chosen;
        });

        if (chosen) {
            mountWhenShown();
        }
    };

    form.addEventListener('change', (event) => {
        if (event.target?.name === field) {
            pending = null;
            sync();
        }
    });

    /* Capture phase: the inline flow replaces the form's own (hosted redirect) submit. */
    form.addEventListener(
        'submit',
        async (event) => {
            if (gatewayValue(form, field) !== value) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (form.dataset.stripeBusy === '1' || !card.validate()) {
                return;
            }

            form.dataset.stripeBusy = '1';
            const restore = busy(form);

            try {
                if (!pending) {
                    const payload = await http.post(form.action, new FormData(form), { form, silent: true, followRedirect: false, toast: false });

                    if (!payload?.data?.inline) {
                        if (payload?.redirect) {
                            window.location.assign(payload.redirect);
                            return;
                        }

                        throw new Error('stripe');
                    }

                    pending = payload.data.inline;
                }

                const result = await card.confirm(pending.client_secret);

                if (result.ok) {
                    window.location.assign(pending.return_url);
                    return;
                }

                if (!result.retry) {
                    pending = null;
                }
            } catch (failure) {
                pending = null;
                card.showError(failure?.payload?.message || panel.dataset.cardErrorText || '');

                if (failure?.payload?.redirect) {
                    window.location.assign(failure.payload.redirect);
                    return;
                }
            }

            delete form.dataset.stripeBusy;
            restore();
        },
        true,
    );

    sync();
}

/** Binds every inline Stripe card panel inside a form under root. */
export function bindStripeCardForms(root = document) {
    root.querySelectorAll('form [data-stripe-card]').forEach(bindForm);
}
