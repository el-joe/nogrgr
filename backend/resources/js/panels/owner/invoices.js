/**
 * Owner › Invoices (OWN-005): [data-print] opens the print dialog, [data-autoprint]
 * (print view) opens it on load; printing always uses the light theme.
 */
let themeBound = false;

export default function initOwnerInvoices(root = document) {
    const page = root.querySelector('[data-page="owner-invoices"]');

    if (!page) {
        return;
    }

    if (!themeBound) {
        themeBound = true;
        const html = document.documentElement;
        let wasDark = false;

        window.addEventListener('beforeprint', () => {
            wasDark = html.classList.contains('dark');
            html.classList.remove('dark');
        });
        window.addEventListener('afterprint', () => html.classList.toggle('dark', wasDark));
    }

    page.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));

    if (page.matches('[data-autoprint]')) {
        window.setTimeout(() => window.print(), 300);
    }
}
