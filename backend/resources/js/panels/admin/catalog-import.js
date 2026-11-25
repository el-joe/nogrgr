/**
 * Admin › Catalog › Products › Import (ADM-013, page key "admin-catalog-import"):
 *  - [data-import-page][data-progress-url]  polls the run's progress JSON every few
 *    seconds while data-active="1": bars ([data-progress-bar]), counters, status badge
 *    and title ([data-status-value]), pages line and the pause / resume / cancel buttons; reloads the log and
 *    history tables on each change; stops (and refreshes once) when the run ends.
 *  - [data-import-action]  pause / resume / cancel (cancel asks first) → PATCH data-url.
 *  - #run-import-modal  loads the source preview when opened (loading / error + retry),
 *    re-loads it when the driver changes, and posts form[data-import-form].
 *  - form[data-unsaved-form] (category mapping) gets the unsaved-changes bar.
 */
import { http } from '../../core/http';
import { numberLocale } from '../../core/i18n';
import { confirmDialog } from '../ui/confirm';
import { bindUnsavedForms } from './unsaved-form.js';

const POLL_INTERVAL = 3000;
const POLL_BACKOFF = 10000;

const format = (value) => new Intl.NumberFormat(numberLocale()).format(Number(value) || 0);

function setBar(page, key, value) {
    const bar = page.querySelector(`[data-progress-bar="${key}"]`);
    const percent = Math.max(0, Math.min(100, Number(value?.percent ?? bar?.dataset.percent ?? 0)));

    if (bar) {
        bar.style.width = `${percent}%`;
        bar.dataset.percent = String(percent);
    }

    page.querySelector(`[data-progress-track="${key}"]`)?.setAttribute('aria-valuenow', String(percent));

    const text = page.querySelector(`[data-progress-text="${key}"]`);

    if (text && value) {
        text.textContent = `${format(value.done)} / ${format(value.total)}`;
    }
}

function reloadTables() {
    ['import-log', 'import-runs'].forEach((id) => {
        document.getElementById(id)?.closest('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
    });
}

function render(page, data) {
    setBar(page, 'products', data.products);
    setBar(page, 'images', data.images);

    Object.entries(data.counts ?? {}).forEach(([key, value]) => {
        const node = page.querySelector(`[data-progress-count="${key}"]`);

        if (node) {
            node.textContent = format(value);
        }
    });

    const pages = page.querySelector('[data-progress-pages]');

    if (pages && data.pages) {
        pages.textContent = pages.dataset.template.replace('__done__', format(data.pages.done)).replace('__total__', format(data.pages.total));
    }

    if (data.status) {
        page.querySelectorAll('#import-progress [data-status-value]').forEach((node) => {
            node.hidden = node.dataset.statusValue !== data.status;
        });
    }

    const failure = page.querySelector('[data-progress-failure]');

    if (failure) {
        failure.hidden = !data.failure;
        failure.textContent = data.failure ?? '';
    }

    page.querySelector('[data-progress-live]')?.toggleAttribute('hidden', !data.active);

    const controls = {
        pause: data.can_pause,
        resume: data.can_resume,
        cancel: data.can_cancel,
    };

    Object.entries(controls).forEach(([action, visible]) => {
        page.querySelector(`[data-import-action="${action}"]`)?.toggleAttribute('hidden', !visible);
    });
}

function startPolling(page) {
    const url = page.dataset.progressUrl;

    if (!url) {
        return;
    }

    page.querySelectorAll('[data-progress-bar]').forEach((bar) => setBar(page, bar.dataset.progressBar, null));

    let last = '';

    const tick = async () => {
        if (page.dataset.active !== '1') {
            return;
        }

        let delay = POLL_INTERVAL;

        try {
            const payload = await http.get(url, null, { silent: true, toast: false });
            const data = payload?.data ?? {};
            const signature = JSON.stringify([data.status, data.counts, data.products, data.images]);
            page.querySelector('[data-progress-poll-error]')?.setAttribute('hidden', '');

            if (signature !== last) {
                last = signature;
                render(page, data);
                reloadTables();
            }

            if (!data.active) {
                page.dataset.active = '0';
                window.location.reload();

                return;
            }
        } catch {
            page.querySelector('[data-progress-poll-error]')?.removeAttribute('hidden');
            delay = POLL_BACKOFF;
        }

        window.setTimeout(tick, delay);
    };

    window.setTimeout(tick, POLL_INTERVAL);
}

function bindActions(page) {
    page.addEventListener('click', async (event) => {
        const button = event.target.closest?.('[data-import-action]');

        if (!button || button.hasAttribute('data-loading')) {
            return;
        }

        if (button.dataset.importAction === 'cancel') {
            const ok = await confirmDialog({
                title: button.dataset.confirmTitle,
                message: button.dataset.confirmMessage,
                confirmLabel: button.dataset.confirmLabel,
                tone: 'danger',
            });

            if (!ok) {
                return;
            }
        }

        button.setAttribute('data-loading', '');
        button.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.patch(button.dataset.url);
            render(page, payload?.data ?? {});

            if (payload?.data && !payload.data.active) {
                window.location.reload();
            } else if (page.dataset.active !== '1') {
                page.dataset.active = '1';
                startPolling(page);
            }
        } catch {
            // toast shown by the http client
        } finally {
            button.removeAttribute('data-loading');
            button.removeAttribute('aria-busy');
        }
    });
}

function bindRunModal() {
    const form = document.querySelector('[data-import-form]');

    if (!form) {
        return;
    }

    const modal = form.closest('[data-overlay]');
    const loading = form.querySelector('[data-preview-loading]');
    const error = form.querySelector('[data-preview-error]');
    const errorMessage = form.querySelector('[data-preview-error-message]');
    const body = form.querySelector('[data-preview-body]');
    let controller = null;

    const loadPreview = async () => {
        controller?.abort();
        controller = new AbortController();
        loading.hidden = false;
        error.hidden = true;
        body.hidden = true;

        try {
            const driver = form.querySelector('[data-import-driver]')?.value ?? '';
            const payload = await http.get(form.dataset.previewUrl, { driver }, { silent: true, toast: false, signal: controller.signal });
            body.innerHTML = payload?.data?.html ?? '';
            body.hidden = false;
        } catch (exception) {
            if (exception?.name === 'AbortError') {
                return;
            }

            errorMessage.textContent = exception?.payload?.message ?? '';
            error.hidden = false;
        } finally {
            loading.hidden = true;
        }
    };

    modal?.addEventListener('overlay:open', loadPreview);
    form.querySelector('[data-import-driver]')?.addEventListener('change', loadPreview);
    form.querySelector('[data-preview-retry]')?.addEventListener('click', loadPreview);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.querySelector('[form="run-import-form"][type="submit"]');
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');

        try {
            await http.post(form.action, new FormData(form), { form });
        } catch {
            // field errors / toast shown by the http client
        } finally {
            submit?.removeAttribute('data-loading');
            submit?.removeAttribute('aria-busy');
        }
    });
}

export default function initCatalogImport(root) {
    bindUnsavedForms(root);

    const page = root.querySelector('[data-import-page]');

    if (!page) {
        return;
    }

    page.querySelectorAll('[data-progress-bar]').forEach((bar) => setBar(page, bar.dataset.progressBar, null));
    bindActions(page);
    bindRunModal();

    if (page.dataset.active === '1') {
        startPolling(page);
    }
}
