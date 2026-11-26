/**
 * Owner portal › Notifications (page key "owner-notifications", OWN-008): All / Unread
 * tabs drive the hidden `state` filter of the list; row mark read / unread, live pushes
 * and "Mark all as read" reuse the admin inbox behaviour and resync the bell.
 */
import initNotifications from '../admin/notifications.js';

function bindTabs(root) {
    const input = root.querySelector('[data-owner-state]');
    const tabs = [...root.querySelectorAll('[data-state-tab]')];

    if (!input) {
        return;
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            input.value = tab.dataset.stateTab;
            tabs.forEach((other) => other.setAttribute('aria-selected', String(other === tab)));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

export default function initOwnerNotifications(root) {
    initNotifications(root);
    bindTabs(root);

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('[data-owner-mark-all]')) {
            root.querySelector('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
            window.dispatchEvent(new CustomEvent('panel-notifications:changed'));
            event.target.setAttribute('hidden', '');
            root.querySelectorAll('[data-state-count="unread"]').forEach((count) => { count.textContent = '0'; });
        }
    });
}
