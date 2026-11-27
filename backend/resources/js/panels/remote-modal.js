/**
 * Modals whose body is fetched from the server (admin create / edit, audit detail).
 *
 *   <button data-remote-modal="admin-modal" data-url="/admin/system/admins/5/edit">
 *   <x-ui::modal id="admin-modal"> [data-remote-loading] [data-remote-error] [data-remote-body]
 *
 * Opening shows the skeleton, fetches the HTML fragment, injects it and wires its
 * fields (Select2, switches…). The fragment's root may carry data-modal-title /
 * data-modal-subtitle (and data-submit-label / data-submit-disabled for [data-remote-submit]). A data-ajax
 * form inside closes the modal on success, reloads the page's table and refreshes its
 * [data-live] regions (counts, quota lines; see ui/live.js). Failures
 * show the error state with Retry.
 */
import { initUi, openOverlay, closeOverlay, notifyChanged } from './ui/index.js';
import { initSelect2 } from './select2';

async function load(modal, url) {
    const loading = modal.querySelector('[data-remote-loading]');
    const error = modal.querySelector('[data-remote-error]');
    const body = modal.querySelector('[data-remote-body]');
    const submit = modal.querySelector('[data-remote-submit]');

    loading.hidden = false;
    error.hidden = true;
    body.hidden = true;
    body.replaceChildren();
    submit?.setAttribute('disabled', '');
    modal.dataset.remoteUrl = url;

    try {
        const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        body.innerHTML = await response.text();
    } catch {
        loading.hidden = true;
        error.hidden = false;
        return;
    }

    const root = body.firstElementChild;
    const title = modal.querySelector(`#${CSS.escape(modal.id)}-title`);
    const subtitle = modal.querySelector(`#${CSS.escape(modal.id)}-subtitle`);

    if (root?.dataset.modalTitle && title) {
        title.textContent = root.dataset.modalTitle;
    }

    if (subtitle) {
        subtitle.textContent = root?.dataset.modalSubtitle ?? '';
    }

    if (submit && root?.dataset.submitLabel) {
        const label = [...submit.childNodes].reverse().find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');

        if (label) {
            label.textContent = ` ${root.dataset.submitLabel}`;
        }
    }

    loading.hidden = true;
    body.hidden = false;
    // data-submit-disabled on the fragment root: nothing to submit (e.g. no missing keys).
    submit?.toggleAttribute('disabled', root?.hasAttribute('data-submit-disabled') ?? false);
    initUi(body);
    initSelect2(body);

    body.querySelectorAll('form').forEach((form) => {
        form.addEventListener('input', () => (form.dataset.dirty = ''));
    });

    (body.querySelector('[data-autofocus]') ?? body.querySelector('input:not([type="hidden"]), select, textarea'))?.focus({ preventScroll: true });
}

export function bindRemoteModals(root = document) {
    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-remote-modal]');

        if (opener) {
            event.preventDefault();
            const modal = document.getElementById(opener.dataset.remoteModal);

            if (!modal) {
                return;
            }

            openOverlay(modal);
            load(modal, opener.dataset.url);
            return;
        }

        const retry = event.target.closest('[data-remote-retry]');

        if (retry) {
            const modal = retry.closest('.ui-overlay');

            if (modal?.dataset.remoteUrl) {
                load(modal, modal.dataset.remoteUrl);
            }
        }
    });

    document.querySelectorAll('.ui-overlay [data-remote-body]').forEach((body) => {
        const modal = body.closest('.ui-overlay');

        modal.addEventListener('ajax:success', (event) => {
            delete event.target.dataset.dirty;
            closeOverlay(modal, { force: true });
            root.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } })));
            notifyChanged({ source: 'modal' });
        });
    });
}
