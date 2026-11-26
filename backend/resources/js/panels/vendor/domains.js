/**
 * Vendor › Settings › Domains (VEN-082 / 082a), loaded for [data-page="vendor-domains"]:
 * "Re-check DNS" posts the queued DNS / SSL check, then reloads the page so the records
 * panel shows the new result. Adding a domain follows the server redirect to its records.
 */
import { http } from '../../core/http.js';

const RELOAD_DELAY_MS = 1500;

export default function initVendorDomains(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-domain-check]');

        if (!button || button.hasAttribute('data-loading')) {
            return;
        }

        event.preventDefault();
        button.setAttribute('data-loading', '');
        button.setAttribute('aria-busy', 'true');

        try {
            await http.post(button.dataset.url, null);
            window.setTimeout(() => window.location.reload(), RELOAD_DELAY_MS);
        } catch {
            // The http client already showed the error toast.
            button.removeAttribute('data-loading');
            button.removeAttribute('aria-busy');
        }
    });
}
