/**
 * Vendor › Finance › Invoices (VEN-061 / 061a), loaded for [data-page="vendor-invoices"]:
 *
 *   - [data-invoice-view] (eye in a row) loads the invoice detail fragment into the side
 *     panel [data-invoice-panel] (skeleton, error + retry), keeps ?invoice= in the URL and
 *     marks the row; without JS the link opens the invoice page
 *   - Pay now ([data-invoice-pay]) fills the invoice checkout modal (subscription.js)
 *   - print view: [data-print] button and [data-autoprint] (print dialog on load,
 *     "Save as PDF" there)
 */
import initVendorSubscription from './subscription.js';

function bindPrint(root) {
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-print]')) {
            event.preventDefault();
            window.print();
        }
    });

    if (root.querySelector('[data-autoprint]')) {
        window.setTimeout(() => window.print(), 300);
    }
}

function markRow(root, id) {
    root.querySelectorAll('tr[data-invoice-row]').forEach((row) => {
        row.classList.toggle('bg-brand-subtle', row.dataset.invoiceRow === String(id));
    });
}

function bindPanel(root) {
    const panel = root.querySelector('[data-invoice-panel]');

    if (!panel) {
        return;
    }

    const loading = panel.querySelector('[data-invoice-panel-loading]');
    const error = panel.querySelector('[data-invoice-panel-error]');
    const body = panel.querySelector('[data-invoice-panel-body]');
    let current = null;

    const load = async (url) => {
        current = url;
        loading.hidden = false;
        error.hidden = true;
        body.hidden = true;

        try {
            const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            body.innerHTML = await response.text();
            body.hidden = false;

            const id = body.querySelector('[data-invoice-detail]')?.dataset.invoiceDetail;
            markRow(root, id);

            if (id) {
                const address = new URL(window.location.href);
                address.searchParams.set('invoice', id);
                window.history.replaceState(null, '', address);
            }

            if (window.matchMedia('(max-width: 1279px)').matches) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch {
            error.hidden = false;
        } finally {
            loading.hidden = true;
        }
    };

    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-invoice-view]');

        if (opener && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            load(opener.dataset.url || opener.getAttribute('href'));
            return;
        }

        if (event.target.closest('[data-invoice-panel-retry]') && current) {
            load(current);
        }
    });

    /* Keep the selected row marked after every table redraw. */
    const tbody = root.querySelector('table#vendor-billing-invoices')?.tBodies[0];

    if (tbody) {
        new MutationObserver(() => markRow(root, body.querySelector('[data-invoice-detail]')?.dataset.invoiceDetail)).observe(tbody, { childList: true });
    }
}

export default function initVendorInvoices(root) {
    bindPrint(root);
    bindPanel(root);
    initVendorSubscription(root);
}
