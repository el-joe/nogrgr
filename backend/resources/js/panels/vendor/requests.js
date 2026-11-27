/**
 * Vendor › Requests (page key "vendor-requests"): request lists, new request forms and the
 * detail page with the shared live conversation (../request-thread.js).
 *  - confirm:done inside [data-reload-on-change] (cancel request) → reload the page.
 *  - [data-gateway-pick] radio cards submit their payment form's gateway field.
 *  - [data-remote-modal] "New request" opens the request form modal (QA-FINAL-005).
 */
import { bindThread } from '../request-thread.js';
import { bindRemoteModals } from '../remote-modal.js';

export default function initVendorRequests(root = document) {
    bindRemoteModals(root);
    root.querySelectorAll('[data-thread]').forEach(bindThread);

    document.addEventListener('confirm:done', (event) => {
        if (event.target.closest?.('[data-reload-on-change]')) {
            window.location.reload();
        }
    });
}
