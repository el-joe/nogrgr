/**
 * Admin › Requests center (page key "admin-requests"): lists, detail pages and the shared
 * request conversation. Behaviour by data attributes:
 *  - [data-request-status]       opens #request-status-modal for data-url / data-current;
 *                                form[data-request-status-form] PATCHes the status.
 *  - [data-request-action]       sends data-method to data-url with data-payload (JSON),
 *                                after a confirm when data-confirm-text is set.
 *  - select[data-request-field]  PATCHes {name: value} to data-url on change (status / priority).
 *  - form[data-reload-form], form[data-payment-form], confirm:done inside
 *    [data-reload-on-change] → reload the page; on list pages the table reloads instead.
 *  - form[data-decision-form]    submit buttons with data-decision set the hidden decision.
 *  - [data-thread]               conversation: Enter-to-send composer with attachments,
 *                                live refresh over Reverb (private-requests.<kind>.<id>)
 *                                with a 15 s polling fallback, read receipts.
 */
import { http } from '../../core/http';
import { t } from '../../core/i18n';
import { $ } from '../../core/select2-lib.js';
import { confirmDialog } from '../ui/confirm';
import { openOverlay, closeOverlay } from '../ui/overlays';
import { bindThread } from '../request-thread.js';

function refresh(trigger) {
    const card = trigger?.closest?.('[data-datatable-card]') ?? document.querySelector('[data-requests-list] [data-datatable-card]');

    if (card) {
        card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
    } else {
        window.location.reload();
    }
}

function bindStatusModal() {
    const form = document.querySelector('form[data-request-status-form]');

    if (!form) {
        return;
    }

    const modal = form.closest('[data-overlay]');
    const select = form.querySelector('select[name="status"]');
    let source = null;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-request-status]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        source = trigger;
        form.action = trigger.dataset.url;
        $(select).val(trigger.dataset.current).trigger('change');
        openOverlay(modal);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.querySelector(`[form="${form.id}"][type="submit"]`);
        submit?.setAttribute('data-loading', '');

        try {
            await http.patch(form.action, new FormData(form), { form });
            await closeOverlay(modal, { force: true });
            refresh(source);
        } catch {
            // field errors / toast handled by http.js
        } finally {
            submit?.removeAttribute('data-loading');
        }
    });
}

function bindActions() {
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest?.('[data-request-action]');

        if (!trigger || trigger.hasAttribute('data-loading')) {
            return;
        }

        event.preventDefault();

        if (trigger.dataset.confirmText) {
            const ok = await confirmDialog({
                title: trigger.dataset.confirmTitle,
                message: trigger.dataset.confirmText,
                tone: trigger.dataset.confirmTone || 'danger',
                confirmLabel: trigger.textContent.trim() || t('ui.confirm'),
            });

            if (!ok) {
                return;
            }
        }

        trigger.setAttribute('data-loading', '');
        trigger.setAttribute('aria-busy', 'true');

        try {
            let payload = null;

            try {
                payload = trigger.dataset.payload ? JSON.parse(trigger.dataset.payload) : null;
            } catch {
                payload = null;
            }

            await http.request(trigger.dataset.method || 'POST', trigger.dataset.url, { data: payload });
            refresh(trigger.closest('[data-datatable-card]') ? trigger : null);
        } catch {
            // http.js shows the error toast
        } finally {
            trigger.removeAttribute('data-loading');
            trigger.removeAttribute('aria-busy');
        }
    });
}

function bindFields() {
    document.querySelectorAll('select[data-request-field]').forEach((select) => {
        let previous = select.value;

        select.addEventListener('change', async () => {
            if (select.value === previous) {
                return;
            }

            try {
                await http.patch(select.dataset.url, { [select.name]: select.value });
                previous = select.value;
                window.location.reload();
            } catch {
                $(select).val(previous).trigger('change');
            }
        });
    });
}

function bindReloads() {
    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('form[data-reload-form], form[data-payment-form]')) {
            window.location.reload();
        }
    });

    document.addEventListener('confirm:done', (event) => {
        if (event.target.closest?.('[data-reload-on-change]') || event.target.closest?.('#request-payments')) {
            window.location.reload();
        }
    });

    document.querySelectorAll('form[data-decision-form]').forEach((form) => {
        const input = form.querySelector('[data-decision-input]');

        form.querySelectorAll('[data-decision]').forEach((button) => {
            button.addEventListener('click', () => {
                input.value = button.dataset.decision;
            });
        });
    });
}

export default function initRequests() {
    bindStatusModal();
    bindActions();
    bindFields();
    bindReloads();
    document.querySelectorAll('[data-thread]').forEach(bindThread);
}
