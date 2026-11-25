/**
 * Admin finance pages (ADM-050…053, decision #72):
 *  - detail / edit / mark-settlement-paid modals ([data-remote-modal], remote-modal.js);
 *  - [data-reload-param] select (create payout store picker): reloads the page with the
 *    chosen store so its available balance and bank details on file are shown;
 *  - statement print ([data-print], [data-autoprint]).
 */
import { bindRemoteModals } from '../remote-modal.js';
import { initOrderPage } from '../orders-shared.js';

function bindReload(root) {
    root.querySelectorAll('select[data-reload-param]').forEach((select) => {
        const reload = () => {
            const url = new URL(window.location.href);

            if (select.value) {
                url.searchParams.set(select.dataset.reloadParam, select.value);
            } else {
                url.searchParams.delete(select.dataset.reloadParam);
            }

            if (url.href !== window.location.href) {
                window.location.assign(url.href);
            }
        };

        select.addEventListener('change', reload); // select2.js bridges its events to native change
    });
}

export default function init(root) {
    initOrderPage(root);
    bindReload(root);
}
