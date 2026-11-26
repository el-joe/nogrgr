/**
 * Admin › Storefront › Default banners (ADM-085): positions in the card list and
 * drag & drop sorting of the server-side DataTable rows (SortableJS), saved on drop.
 *
 *   [data-banner-sort="<url>"]  the datatable card (manage permission only)
 *   tr[data-sort-item][data-id] rows (DataTables row attributes), [data-drag-handle]
 *   [data-sort-position]        position number in each row
 */
import { $ } from '../vendor-libs';
import { http } from '../../core/http.js';
import { loadSortable } from '../core.js';

function renumber(tbody) {
    tbody.querySelectorAll('tr[data-sort-item]').forEach((row, index) => {
        const position = row.querySelector('[data-sort-position]');

        if (position) {
            position.textContent = String(index + 1);
        }
    });
}

export default function initBanners(page) {
    const table = page.querySelector('table#banners');
    const tbody = table?.tBodies[0];

    if (!table || !tbody) {
        return;
    }

    const card = table.closest('[data-datatable-card]');
    const sortUrl = card?.dataset.bannerSort;

    $(table).on('draw.dt', () => renumber(tbody));
    renumber(tbody);

    if (!sortUrl) {
        return;
    }

    loadSortable().then((Sortable) => {
        Sortable.create(tbody, {
            handle: '[data-drag-handle]',
            draggable: 'tr[data-sort-item]',
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd: async (event) => {
                if (event.oldIndex === event.newIndex) {
                    return;
                }

                renumber(tbody);

                try {
                    await http.post(sortUrl, { ids: [...tbody.querySelectorAll('tr[data-sort-item]')].map((row) => Number(row.dataset.id)) });
                } catch {
                    /* http.js already showed the error toast; reload the real order. */
                    card.dispatchEvent(new CustomEvent('datatable:reload'));
                }
            },
        });
    });
}
