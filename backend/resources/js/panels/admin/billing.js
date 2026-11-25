/**
 * Billing admin pages (plans form, subscriptions, invoices):
 *  - [data-unsaved-form]  shows its [data-unsaved-bar] once edited; [data-unsaved-discard] reloads.
 *  - [data-export-for="<table id>"]  export link that carries the table's current filters.
 *  - [data-print]  opens the print dialog; [data-autoprint] (print view) opens it on load.
 *  - Printing always uses the light theme (the .dark class is lifted while printing).
 */
import { collectFilters } from '../ui/filters';

function bindUnsavedBar(root) {
    root.querySelectorAll('form[data-unsaved-form]').forEach((form) => {
        const bar = form.querySelector('[data-unsaved-bar]');

        if (!bar) {
            return;
        }

        const show = () => {
            bar.hidden = false;
        };

        form.addEventListener('input', show);
        form.addEventListener('change', show);
        form.addEventListener('ajax:success', () => {
            bar.hidden = true;
        });
        bar.querySelector('[data-unsaved-discard]')?.addEventListener('click', () => window.location.reload());
    });
}

function bindExportLinks(root) {
    root.querySelectorAll('a[data-export-for]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const url = new URL(link.href, window.location.origin);

            Object.entries(collectFilters(link.dataset.exportFor)).forEach(([key, value]) => {
                (Array.isArray(value) ? value : [value]).forEach((item) => {
                    if (item !== '' && item !== null && item !== undefined) {
                        url.searchParams.append(Array.isArray(value) ? `${key}[]` : key, item);
                    }
                });
            });

            window.location.assign(url.toString());
        });
    });
}

let printThemeBound = false;

function bindPrinting(root) {
    if (!printThemeBound) {
        printThemeBound = true;
        const html = document.documentElement;
        let wasDark = false;

        window.addEventListener('beforeprint', () => {
            wasDark = html.classList.contains('dark');
            html.classList.remove('dark');
        });
        window.addEventListener('afterprint', () => {
            html.classList.toggle('dark', wasDark);
        });
    }

    root.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    if (root.matches('[data-autoprint]')) {
        window.setTimeout(() => window.print(), 300);
    }
}

export default function initBilling(element) {
    bindUnsavedBar(element);
    bindExportLinks(element);
    bindPrinting(element);
}
