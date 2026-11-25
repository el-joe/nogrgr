/**
 * Ajax forms with an inline success state: <div data-ajax-state> wraps a
 * <form data-ajax data-inline-success> and a hidden [data-success] block. On success
 * the form is reset and swapped for the confirmation; [data-success-reset] brings the
 * form back. Validation (422) and throttling (429) are handled by core/http.
 */
export function initInlineForms(root = document) {
    root.addEventListener('ajax:success', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-inline-success')) {
            return;
        }

        const state = form.closest('[data-ajax-state]');
        const success = state?.querySelector('[data-success]');
        form.reset();

        const started = form.querySelector('input[name="form_started_at"]');
        if (started) {
            started.value = String(Math.floor(Date.now() / 1000));
        }

        if (!success) {
            return;
        }

        const message = event.detail?.message;
        const text = success.querySelector('[data-success-message]');
        if (message && text) {
            text.textContent = message;
        }

        form.hidden = true;
        success.hidden = false;
        success.setAttribute('tabindex', '-1');
        success.focus({ preventScroll: true });
    });

    root.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-success-reset]');

        if (!button) {
            return;
        }

        const state = button.closest('[data-ajax-state]');
        const form = state?.querySelector('form[data-inline-success]');
        state?.querySelector('[data-success]')?.setAttribute('hidden', '');
        if (form) {
            form.hidden = false;
            form.querySelector('input:not([type="hidden"]):not([tabindex="-1"]), textarea')?.focus();
        }
    });
}
