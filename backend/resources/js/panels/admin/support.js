/**
 * Admin › Support tickets (page key "admin-support", ADM-116 / 116a):
 *  - [data-thread]          live conversation (panels/request-thread.js) with the
 *                           "Internal note" switch ([data-thread-extra]); the composer
 *                           placeholder and tint follow the switch.
 *  - select[data-ticket-field]  PATCHes {name: value} to [data-ticket-detail] data-update-url
 *                           on change (status / priority / assignee), then reloads so the
 *                           badge, SLA and Resolve action match; reverts on failure.
 *  - Resolve (confirm dialog) follows the JSON redirect.
 */
import { http } from '../../core/http';
import { $ } from '../../core/select2-lib.js';
import { bindThread } from '../request-thread.js';

function bindInternalToggle(root) {
    const toggle = root.querySelector('[data-internal-toggle]');
    const body = root.querySelector('[data-thread-body]');
    const composer = root.querySelector('[data-composer]');

    if (!toggle || !body) {
        return;
    }

    const sync = () => {
        body.placeholder = toggle.checked ? body.dataset.placeholderNote : body.dataset.placeholderReply;
        composer?.classList.toggle('bg-warning-bg', toggle.checked);
    };

    toggle.addEventListener('change', sync);
    root.querySelector('form[data-thread-form]')?.addEventListener('ajax:success', sync);
    sync();
}

function bindFields(root) {
    const detail = root.querySelector('[data-ticket-detail]');

    if (!detail) {
        return;
    }

    detail.querySelectorAll('select[data-ticket-field]').forEach((select) => {
        let previous = select.value;

        select.addEventListener('change', async () => {
            if (select.value === previous) {
                return;
            }

            try {
                await http.patch(detail.dataset.updateUrl, { [select.name]: select.value === '' ? null : select.value });
                previous = select.value;
                window.location.reload();
            } catch {
                $(select).val(previous).trigger('change');
            }
        });
    });
}

export default function initSupport(root = document) {
    root.querySelectorAll('[data-thread]').forEach(bindThread);
    bindInternalToggle(root);
    bindFields(root);
}
