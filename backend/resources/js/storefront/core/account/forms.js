/**
 * Storefront auth + account forms (all [data-ajax], JSON contract):
 *   password eye            [data-password-toggle="#id"]
 *   422 banner              [data-form-error] inside the form
 *   reset after success     form[data-reset-on-success]   (change password)
 *   forgot password         form[data-forgot-form]  → [data-sent-state]
 *   reset password          form[data-reset-form]   → [data-expired-state] on a bad / expired token
 *   profile                 [data-profile-card]: view ⇄ edit, view values from the response
 *   delete account          form[data-delete-account-form]: its password error comes from its
 *                           own 422 JSON (the change-password form shares the key)
 */
import { clearErrors } from '../../../core/form-errors';
import { closeSheet } from '../motion/sheet';

function bindPasswordToggles() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest?.('[data-password-toggle]');
        const input = toggle ? document.querySelector(toggle.dataset.passwordToggle) : null;

        if (input) {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(show));
        }
    });
}

function bindProfile(card) {
    const view = card.querySelector('[data-profile-view]');
    const form = card.querySelector('[data-profile-form]');
    const edit = card.querySelector('[data-profile-edit]');
    const editing = card.querySelector('[data-profile-editing]');

    if (!view || !form) {
        return;
    }

    const toggle = (on) => {
        view.hidden = on;
        form.hidden = !on;
        edit.hidden = on;
        if (editing) {
            editing.hidden = !on;
        }
        if (on) {
            form.querySelector('input:not([type="hidden"])')?.focus({ preventScroll: true });
        } else {
            form.reset();
            clearErrors(form);
            edit.focus({ preventScroll: true });
        }
    };

    edit?.addEventListener('click', () => toggle(true));
    card.querySelector('[data-profile-cancel]')?.addEventListener('click', () => toggle(false));
    form.addEventListener('ajax:success', (event) => {
        const data = event.detail?.data ?? {};
        const locales = (() => {
            try {
                return JSON.parse(view.querySelector('[data-profile-value="locale"]')?.dataset.localeNames ?? '{}');
            } catch {
                return {};
            }
        })();

        Object.entries({ name: data.name, email: data.email, phone: data.phone || '—', locale: locales[data.locale] ?? data.locale }).forEach(([key, value]) => {
            const node = view.querySelector(`[data-profile-value="${key}"]`);

            if (node && value !== undefined && value !== null) {
                node.textContent = value;
            }
        });

        // New defaults so Cancel / the next edit start from the saved values.
        form.querySelectorAll('input:not([type="hidden"]), select').forEach((field) => {
            if (field.tagName === 'SELECT') {
                [...field.options].forEach((option) => {
                    option.defaultSelected = option.selected;
                });
            } else {
                field.defaultValue = field.value;
            }
        });
        toggle(false);
    });
}

export function initAccountForms(root = document) {
    if (!document.__sfAccountForms) {
        document.__sfAccountForms = true;
        bindPasswordToggles();

        document.addEventListener('ajax:error', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || event.detail?.status !== 422) {
                return;
            }

            form.querySelector('[data-form-error]')?.removeAttribute('hidden');

            if (form.matches('[data-reset-form]')) {
                const errors = event.detail.errors ?? {};
                const emailVisible = form.querySelector('input[name="email"]:not([type="hidden"])');

                if (errors.email && (!emailVisible || Object.keys(errors).length === 1)) {
                    form.hidden = true;
                    const expired = document.querySelector('[data-expired-state]');
                    if (expired) {
                        expired.hidden = false;
                        expired.querySelector('a')?.focus({ preventScroll: true });
                    }
                }
            }
        });

        document.addEventListener('ajax:success', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.querySelector('[data-form-error]')?.setAttribute('hidden', '');

            if (form.matches('[data-reset-on-success]')) {
                form.reset();
                clearErrors(form);
            }

            if (form.matches('[data-forgot-form]')) {
                const sent = document.querySelector('[data-sent-state]');
                if (sent) {
                    const text = sent.querySelector('div') ?? sent;
                    if (event.detail?.message) {
                        text.textContent = event.detail.message;
                    }
                    sent.hidden = false;
                }
                form.hidden = true;
            }

            const sheet = form.closest('[data-sheet]');
            if (sheet && !event.detail?.redirect) {
                closeSheet(sheet);
            }
        });
    }

    root.querySelectorAll('[data-profile-card]').forEach((card) => {
        if (!card.__profile) {
            card.__profile = true;
            bindProfile(card);
        }
    });
}
