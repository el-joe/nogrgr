/**
 * "Notify me when it is back" form of an out-of-stock product page (decision #124,
 * partial storefront::partials.stock-alert): posts to the Store API, shows field errors,
 * and swaps the form for a confirmation. A signed-in customer already subscribed sees
 * the confirmation at once (GET status).
 */
import { http, HttpError } from '../../core/http';

function done(form, message) {
    form.querySelector('[data-stock-alert-fields]')?.setAttribute('hidden', '');
    form.querySelector('[data-stock-alert-help]')?.setAttribute('hidden', '');
    const status = form.querySelector('[data-stock-alert-done]');

    if (status) {
        status.textContent = message || status.dataset.already || '';
        status.hidden = false;
    }
}

function bind(form) {
    const error = form.querySelector('[data-stock-alert-error]');

    if (form.dataset.signedIn === '1') {
        http.get(form.action, null, { toast: false, silent: true })
            .then((payload) => {
                if (payload?.data?.subscribed) {
                    done(form);
                }
            })
            .catch(() => {});
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        form.querySelectorAll('[data-error-for]').forEach((node) => (node.hidden = true));
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            const payload = await http.post(form.action, new FormData(form), { toast: false });
            done(form, payload?.message);
        } catch (failure) {
            if (failure instanceof HttpError && failure.status === 422) {
                const errors = failure.errors ?? {};
                let shown = false;

                Object.entries(errors).forEach(([field, messages]) => {
                    const node = form.querySelector(`[data-error-for="${field}"]`);

                    if (node) {
                        node.textContent = messages[0];
                        node.hidden = false;
                        shown = true;
                    }
                });

                if (!shown) {
                    error.textContent = Object.values(errors)[0]?.[0] || failure.payload?.message || '';
                    error.hidden = error.textContent === '';
                }
            }
        } finally {
            submit.disabled = false;
        }
    });
}

export function initStockAlert(root = document) {
    root.querySelectorAll('[data-stock-alert]:not([data-ready])').forEach((form) => {
        form.dataset.ready = '';
        bind(form);
    });
}
