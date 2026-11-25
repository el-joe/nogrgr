/**
 * Admin › Audit log (ADM-112): detail modal (diff) loaded from the server and the
 * CSV export link carrying the table's current filters.
 */
import { bindRemoteModals } from '../remote-modal.js';
import { collectFilters } from '../ui/filters';

function bindExport(root) {
    root.querySelectorAll('a[data-audit-export]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const url = new URL(link.href, window.location.origin);

            Object.entries(collectFilters(link.dataset.auditExport)).forEach(([key, value]) => {
                if (!Array.isArray(value) && value !== '') {
                    url.searchParams.set(key, value);
                }
            });

            window.location.assign(url.toString());
        });
    });
}

export default function initAudit(root) {
    bindRemoteModals(root);
    bindExport(root);
}
