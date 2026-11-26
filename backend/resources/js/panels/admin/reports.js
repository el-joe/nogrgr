/**
 * Admin reporting pages (Shoppers ADM-043):
 *  - [data-export-for="<table id>"]  export link that carries the table's current filters.
 * Report tabs (ADM-070) need no page script: their filters reload the page and the
 * export link already carries them.
 */
import { collectFilters } from '../ui/filters';

export default function init(root) {
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
