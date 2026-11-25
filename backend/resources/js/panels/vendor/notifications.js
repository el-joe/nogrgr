/**
 * Vendor › Notifications (page key "vendor-notifications", VEN-090): same inbox behaviour
 * as the admin page (category chips, row mark read / unread, live reload) plus
 * "Mark all as read" refreshing the list and the bell.
 */
import initNotifications from '../admin/notifications.js';

export default function initVendorNotifications(root) {
    initNotifications(root);

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('[data-notification-mark-all-page]')) {
            root.querySelector('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
            window.dispatchEvent(new CustomEvent('panel-notifications:changed'));
            root.querySelectorAll('[data-unread-pill]').forEach((pill) => pill.setAttribute('hidden', ''));
        }
    });
}
