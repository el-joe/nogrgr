/**
 * Admin › Vendors review pages: pending registrations, compliance / KYC and domains & DNS
 * (one module for page key "admin-vendor-reviews"; behaviour by data attributes):
 *  - [data-reviews-reload]   root whose confirm actions (resend, delete, connect…) reload
 *                            the page when done ([data-no-reload] triggers excepted).
 *  - [data-review-request]   button / menu item that sends data-method to data-url (with
 *                            data-status when present); status flags reload the page,
 *                            other requests (DNS check) only show the toast.
 *  - [data-decision]         compliance header buttons: set the decision status on
 *                            form[data-decision-form] and submit it (note → 422 inline).
 *  - form[data-dns-form]     DNS record modal: [data-dns-new] resets it for create,
 *                            [data-dns-edit='{…}'] fills it for edit; priority is only
 *                            shown for MX records.
 */
import { http } from '../../core/http';
import { clearErrors } from '../../core/form-errors';
import { openOverlay, closeOverlay } from '../ui/overlays';

function reloadDatatable(element) {
    element.closest('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
}

function bindReloads(root) {
    if (root.dataset.reviewsReload === undefined) {
        return;
    }

    document.addEventListener('confirm:done', (event) => {
        if (!event.target.closest?.('[data-no-reload]')) {
            window.location.reload();
        }
    });
}

function bindRequests() {
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest?.('[data-review-request]');

        if (!trigger || trigger.hasAttribute('data-loading')) {
            return;
        }

        event.preventDefault();
        trigger.setAttribute('data-loading', '');
        trigger.setAttribute('aria-busy', 'true');
        const status = trigger.dataset.status;

        try {
            await http.request(trigger.dataset.method || 'POST', trigger.dataset.url, { data: status ? { status } : null });

            if (status) {
                window.location.reload();
            } else {
                reloadDatatable(trigger);
            }
        } catch {
            // http.js already shows the error toast
        } finally {
            trigger.removeAttribute('data-loading');
            trigger.removeAttribute('aria-busy');
        }
    });
}

function bindDecision(root) {
    const form = root.querySelector('form[data-decision-form]');

    if (!form) {
        return;
    }

    const status = form.querySelector('input[name="status"]');

    root.querySelectorAll('[data-decision]').forEach((button) => {
        button.addEventListener('click', () => {
            status.value = button.dataset.decision;
            form.requestSubmit();
        });
    });

    form.addEventListener('ajax:success', () => window.location.reload());
    form.addEventListener('form:errors', () => form.querySelector('[aria-invalid="true"]')?.focus());
}

function bindDnsModal() {
    const form = document.querySelector('form[data-dns-form]');

    if (!form) {
        return;
    }

    const modal = form.closest('[data-overlay]');
    const title = modal?.querySelector(`#${CSS.escape(modal.id)}-title`);
    const method = form.querySelector('[data-dns-method]');
    const type = form.querySelector('[name="type"]');
    const priorityField = form.querySelector('[data-priority-field]');
    const priority = form.querySelector('[name="priority"]');

    const syncPriority = () => {
        const isMx = type.value === 'MX';
        priorityField.hidden = !isMx;
        priority.required = isMx;
    };

    const setValue = (name, value) => {
        const field = form.querySelector(`[name="${name}"]`);

        if (field) {
            field.value = value ?? '';

            if (field.tagName === 'SELECT') {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    };

    const open = (record) => {
        clearErrors(form);
        form.reset();
        const editing = Boolean(record);
        form.action = editing ? record.url : form.dataset.storeUrl;
        method.value = editing ? 'PUT' : 'POST';

        if (title) {
            title.textContent = editing ? form.dataset.titleEdit : form.dataset.titleCreate;
        }

        setValue('type', editing ? record.type : 'CNAME');
        setValue('host', editing ? record.host : '');
        setValue('value', editing ? record.value : '');
        setValue('ttl', editing ? record.ttl : 3600);
        setValue('priority', editing ? record.priority : '');
        setValue('sort_order', editing ? record.sort_order : 100);
        syncPriority();
        delete form.dataset.dirty;
        openOverlay(modal);
        requestAnimationFrame(() => form.querySelector('[name="host"]')?.focus());
    };

    type.addEventListener('change', syncPriority);
    form.addEventListener('input', () => (form.dataset.dirty = ''));

    document.addEventListener('click', (event) => {
        const create = event.target.closest?.('[data-dns-new]');
        const edit = event.target.closest?.('[data-dns-edit]');

        if (create) {
            event.preventDefault();
            open(null);
        } else if (edit) {
            event.preventDefault();

            try {
                open(JSON.parse(edit.dataset.dnsEdit));
            } catch {
                open(null);
            }
        }
    });

    form.addEventListener('ajax:success', async () => {
        delete form.dataset.dirty;
        await closeOverlay(modal, { force: true });
        window.location.reload();
    });

    syncPriority();
}

export default function initVendorReviews(root) {
    const scope = root.querySelector('[data-reviews]') ?? root;

    bindReloads(scope);
    bindRequests();
    bindDecision(scope);
    bindDnsModal();
}
