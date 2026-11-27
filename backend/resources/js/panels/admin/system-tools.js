/**
 * Admin › System tools (page key "admin-system-tools"):
 *  - [data-maintenance]        maintenance windows (ADM-113): [data-maint-new] opens the
 *                              modal for a new window, [data-maint-edit='{…}'] fills it to
 *                              edit (PUT) or view (readonly: fields disabled, no save);
 *                              the store picker shows for scope "store", the start field
 *                              hides when "Start now" is on. Saving / ending / deleting
 *                              reloads the page (banner + table).
 *  - [data-cache-tools]        cache tools (ADM-114): picking a store loads its tab; after
 *                              a clear the "last cleared" lines refresh in place.
 */
import { clearErrors } from '../../core/form-errors';
import { openOverlay, closeOverlay } from '../ui/overlays';

function setField(form, name, value) {
    const field = form.querySelector(`[name="${name}"]:not([type="hidden"])`);

    if (!field) {
        return;
    }

    if (field.type === 'checkbox') {
        field.checked = Boolean(value);
    } else {
        field.value = value ?? '';
    }

    field.dispatchEvent(new Event(field.tagName === 'SELECT' || field.type === 'checkbox' ? 'change' : 'input', { bubbles: true }));
}

function bindMaintenance(root) {
    const form = document.querySelector('form[data-maint-form]');

    if (!root.querySelector('[data-maintenance]') || !form) {
        return;
    }

    const modal = form.closest('[data-overlay]');
    const title = modal?.querySelector(`#${CSS.escape(modal.id)}-title`);
    const method = form.querySelector('[data-maint-method]');
    const scope = form.querySelector('select[name="scope"]');
    const store = form.querySelector('[data-maint-store]');
    const storeSelect = form.querySelector('select[name="tenant_id"]');
    const now = form.querySelector('input[data-maint-now]');
    const start = form.querySelector('[data-maint-start]');
    const save = modal?.querySelector('[data-maint-save]');

    const sync = () => {
        const isStore = scope.value === 'store';
        store.hidden = !isStore;

        if (storeSelect) {
            storeSelect.required = isStore;
        }

        const startNow = Boolean(now?.checked);
        start.hidden = startNow;
        form.querySelector('[name="starts_at"]').required = !startNow;
    };

    const setStore = (id, text) => {
        if (storeSelect) {
            storeSelect.querySelectorAll('option[data-preset]').forEach((option) => option.remove());

            if (id) {
                const option = new Option(text || `#${id}`, String(id), true, true);
                option.dataset.preset = '';
                storeSelect.append(option);
            }

            storeSelect.value = id ? String(id) : '';
            storeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            setField(form, 'tenant_label', text);
        }
    };

    const open = (record) => {
        clearErrors(form);
        form.reset();
        const editing = Boolean(record);
        const readonly = Boolean(record?.readonly);
        form.action = editing ? record.url : form.dataset.storeUrl;
        method.value = editing ? 'PUT' : 'POST';

        if (title) {
            title.textContent = readonly ? form.dataset.titleView : editing ? form.dataset.titleEdit : form.dataset.titleCreate;
        }

        setField(form, 'title', record?.title);
        setField(form, 'scope', record?.scope ?? 'platform');
        setStore(record?.tenant_id, record?.tenant_text);
        setField(form, 'notes', record?.notes);
        setField(form, 'starts_now', !editing);
        setField(form, 'starts_at', record?.starts_at);
        setField(form, 'ends_at', record?.ends_at);
        setField(form, 'message_en', record?.message_en);
        setField(form, 'message_ar', record?.message_ar);
        setField(form, 'allowed_ips', record?.allowed_ips);

        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
            field.disabled = readonly || field.name === 'tenant_label';
        });

        if (save) {
            save.hidden = readonly;
        }

        sync();
        delete form.dataset.dirty;
        openOverlay(modal);
        requestAnimationFrame(() => form.querySelector('[name="title"]')?.focus());
    };

    scope.addEventListener('change', sync);
    now?.addEventListener('change', sync);
    form.addEventListener('input', () => (form.dataset.dirty = ''));

    document.addEventListener('click', (event) => {
        const create = event.target.closest?.('[data-maint-new]');
        const edit = event.target.closest?.('[data-maint-edit]');

        if (create) {
            event.preventDefault();
            open(null);
        } else if (edit) {
            event.preventDefault();

            try {
                open(JSON.parse(edit.dataset.maintEdit));
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

    document.addEventListener('confirm:done', () => window.location.reload());
    sync();
}

async function refreshLastRuns(scope) {
    try {
        const response = await fetch(window.location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');

        html.querySelectorAll('[data-last-run]').forEach((fresh) => {
            scope.querySelector(`[data-last-run="${CSS.escape(fresh.dataset.lastRun)}"]`)?.replaceWith(document.importNode(fresh, true));
        });
    } catch {
        // the toast already confirmed the action; the lines update on the next visit
    }
}

function bindCache(root) {
    const scope = root.querySelector('[data-cache-tools]');

    if (!scope) {
        return;
    }

    const storeForm = scope.querySelector('form[data-cache-store-form]');
    storeForm?.querySelector('select[data-cache-store]')?.addEventListener('change', (event) => {
        if (event.target.value) {
            storeForm.submit();
        }
    });

    scope.addEventListener('confirm:done', () => refreshLastRuns(scope));
}

export default function initSystemTools(root) {
    bindMaintenance(root);
    bindCache(root);
}
