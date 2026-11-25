/**
 * Admin › Notifications page (ADM-007 / 007a):
 *  - category chips drive the hidden `category` filter of the inbox table;
 *  - [data-notification-mark] row actions PATCH read / unread;
 *  - live pushes and deletes reload the table and resync the bell.
 */
import { http } from '../../core/http';

function reloadTable(root) {
    root.querySelector('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
}

function bindChips(root) {
    const input = root.querySelector('[data-notification-category]');
    const chips = [...root.querySelectorAll('[data-category-chip]')];

    if (!input) {
        return;
    }

    const sync = () => chips.forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.categoryChip === input.value)));

    const initial = new URL(window.location.href).searchParams.get('category');

    if (initial && chips.some((chip) => chip.dataset.categoryChip === initial)) {
        input.value = initial;
    }

    sync();

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            input.value = chip.dataset.categoryChip;
            sync();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

function bindRowActions(root) {
    root.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-notification-mark]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        try {
            await http.patch(trigger.dataset.url, { read: trigger.dataset.read });
            reloadTable(root);
            window.dispatchEvent(new CustomEvent('panel-notifications:changed'));
        } catch {
            // http.js already shows the error toast
        }
    });

    root.addEventListener('confirm:done', () => window.dispatchEvent(new CustomEvent('panel-notifications:changed')));
    window.addEventListener('panel-notifications:received', () => reloadTable(root));
    window.addEventListener('panel-notifications:changed', (event) => {
        if (event.detail?.source === 'bell') {
            reloadTable(root);
        }
    });
}

export default function initNotifications(root) {
    bindChips(root);
    bindRowActions(root);
}
