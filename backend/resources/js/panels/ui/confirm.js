/**
 * Confirm Dialog (Figma 32:10454) rendered once per panel page by <x-ui::confirm/>.
 *
 *   const ok = await confirmDialog({ title, message, tone: 'danger', requireText: 'Megz Store' });
 *
 * Declarative use: any element with data-confirm="Message" asks before acting.
 *   <button form="x" data-confirm="…" data-confirm-title="Delete 3 products?" data-confirm-tone="danger">
 *   <a href="…" data-confirm="…" data-confirm-method="DELETE">  → http request, then reloads the
 *      closest datatable (or follows the JSON redirect).
 *   <form data-confirm="…">                                    → asks on submit.
 * data-confirm-require="<name>" makes the user type the item's name (irreversible actions).
 * Focus starts on Cancel.
 */
import { openOverlay, closeOverlay } from './overlays';
import { http } from '../../core/http';
import { t } from '../../core/i18n';

let pending = null;

export function confirmDialog({ title, message = '', confirmLabel = t('ui.confirm'), cancelLabel = t('ui.cancel'), tone = 'danger', requireText = '' } = {}) {
    const dialog = document.getElementById('ui-confirm');

    if (!dialog) {
        return Promise.resolve(window.confirm(message || title));
    }

    pending?.(false);

    const accept = dialog.querySelector('[data-confirm-accept]');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    const requireWrap = dialog.querySelector('[data-confirm-require]');
    const requireInput = requireWrap?.querySelector('input');

    dialog.dataset.tone = tone;
    dialog.querySelector('[data-confirm-title]').textContent = title || t('ui.confirm_title');
    dialog.querySelector('[data-confirm-message]').textContent = message;
    dialog.querySelector('[data-confirm-message]').hidden = !message;
    accept.querySelector('.ui-btn-label').textContent = confirmLabel;
    cancel.querySelector('.ui-btn-label').textContent = cancelLabel;
    accept.classList.toggle('ui-btn-danger', tone === 'danger');
    accept.classList.toggle('ui-btn-primary', tone !== 'danger');

    if (requireWrap && requireInput) {
        requireWrap.hidden = !requireText;
        requireInput.value = '';
        requireWrap.querySelector('[data-confirm-require-name]').textContent = requireText;
        accept.disabled = Boolean(requireText);
        requireInput.oninput = () => {
            accept.disabled = requireInput.value.trim() !== requireText;
        };
    } else {
        accept.disabled = false;
    }

    return new Promise((resolve) => {
        const finish = (result) => {
            pending = null;
            accept.onclick = null;
            dialog.removeEventListener('overlay:close', onClose);
            resolve(result);
        };
        const onClose = () => finish(false);

        pending = finish;
        accept.onclick = () => {
            finish(true);
            closeOverlay(dialog, { force: true });
        };
        dialog.addEventListener('overlay:close', onClose, { once: true });
        openOverlay(dialog);
        requestAnimationFrame(() => (requireText ? requireInput : cancel)?.focus());
    });
}

function optionsFrom(element) {
    return {
        title: element.dataset.confirmTitle || t('ui.confirm_title'),
        message: element.dataset.confirm,
        confirmLabel: element.dataset.confirmLabel || t('ui.confirm'),
        tone: element.dataset.confirmTone || 'danger',
        requireText: element.dataset.confirmRequire || '',
    };
}

let bound = false;

export function initConfirm() {
    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener(
        'click',
        async (event) => {
            const trigger = event.target.closest?.('[data-confirm]:not(form)');

            if (!trigger || trigger.dataset.confirmed !== undefined) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (!(await confirmDialog(optionsFrom(trigger)))) {
                return;
            }

            const method = trigger.dataset.confirmMethod;
            const url = trigger.getAttribute('href') || trigger.dataset.confirmUrl;

            if (method && url) {
                try {
                    await http.request(method, url, { followRedirect: true });
                    trigger.dispatchEvent(new CustomEvent('confirm:done', { bubbles: true }));
                    trigger.closest('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
                } catch {
                    // http.js already shows the error toast
                }

                return;
            }

            if (url && trigger.tagName === 'A') {
                window.location.assign(url);
                return;
            }

            trigger.dataset.confirmed = '';
            trigger.click();
            delete trigger.dataset.confirmed;
        },
        true,
    );

    document.addEventListener(
        'submit',
        async (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || form.dataset.confirm === undefined || form.dataset.confirmed !== undefined) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (await confirmDialog(optionsFrom(form))) {
                form.dataset.confirmed = '';
                form.requestSubmit(event.submitter ?? undefined);
                delete form.dataset.confirmed;
            }
        },
        true,
    );
}
