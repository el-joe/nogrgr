/**
 * Owner › Domains (OWN-004): edit modal loaded from the server (remote modal), the
 * add modal's DNS records preview (refreshed when the store or domain changes,
 * debounced), and "Check again" buttons (queued DNS / SSL check; the detail page
 * reloads to show the new result).
 */
import { http } from '../../core/http.js';
import { bindRemoteModals } from '../remote-modal.js';

const DEBOUNCE_MS = 400;

function bindRecordsPreview(form) {
    const store = form.querySelector('[data-domain-store]');
    const host = form.querySelector('[data-domain-host]');
    const box = form.querySelector('[data-domain-records]');
    const error = form.querySelector('[data-records-error]');
    const loading = box?.querySelector('[data-records-loading]');
    let timer = null;
    let sequence = 0;

    if (!store || !box) {
        return;
    }

    const render = (records) => {
        box.replaceChildren();

        records.forEach((record) => {
            const line = document.createElement('div');
            line.textContent = `${record.type}  ${record.host} → ${record.value}`;
            box.append(line);
        });
    };

    const load = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const current = ++sequence;
            const url = form.dataset.instructionsUrl.replace('__TENANT__', encodeURIComponent(store.value));
            box.replaceChildren(loading ?? '');
            error.hidden = true;

            try {
                const payload = await http.get(url, host?.value ? { host: host.value } : null, { toast: false, silent: true });

                if (current === sequence) {
                    render(payload?.data?.records ?? []);
                }
            } catch {
                if (current === sequence) {
                    box.replaceChildren();
                    error.hidden = false;
                }
            }
        }, DEBOUNCE_MS);
    };

    store.addEventListener('change', load);
    host?.addEventListener('input', load);
    load();
}

function bindChecks(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-domain-check]');

        if (!button) {
            return;
        }

        event.preventDefault();
        button.setAttribute('data-loading', '');
        button.setAttribute('aria-busy', 'true');

        try {
            await http.post(button.dataset.url, null);

            if (document.querySelector('[data-domain-detail]')) {
                window.setTimeout(() => window.location.reload(), 1500);
            } else {
                document.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } })));
            }
        } catch {
            // The http client already showed the error toast.
        } finally {
            button.removeAttribute('data-loading');
            button.removeAttribute('aria-busy');
        }
    });
}

export default function initOwnerDomains(root = document) {
    bindRemoteModals(root);
    bindChecks(root);

    const form = root.querySelector('[data-domain-add]');

    if (form) {
        bindRecordsPreview(form);
    }

    // The edit modal on the detail page: reload to show the new host.
    if (root.querySelector('[data-domain-detail]')) {
        document.getElementById('domain-edit-modal')?.addEventListener('ajax:success', () => window.setTimeout(() => window.location.reload(), 800));
    }
}
