/**
 * Vendor › Your store › Banners (VEN-035 / 035a), loaded for [data-page="vendor-banners"]:
 *
 *   - create / edit modal (remote fragment, VEN-035a)
 *   - positions in the card list + drag & drop of the store's own rows (SortableJS),
 *     saved on drop; platform rows are not draggable and keep their place
 *   - "Show platform default banners" switch [data-banner-defaults="<url>"] (PATCH
 *     {visible}), then the list reloads
 *
 *   [data-banner-sort="<url>"]  the datatable card (manage permission only)
 *   tr[data-sort-item][data-id] own rows, [data-drag-handle], [data-sort-position]
 */
import { $ } from '../vendor-libs';
import { http } from '../../core/http.js';
import { loadSortable } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';

function renumber(tbody) {
    tbody.querySelectorAll('tr[data-id]').forEach((row, index) => {
        const position = row.querySelector('[data-sort-position]');

        if (position) {
            position.textContent = String(index + 1);
        }
    });
}

function reload(card) {
    card?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
}

function bindDefaultsSwitch(page, card) {
    const input = page.querySelector('[data-banner-defaults]');

    input?.addEventListener('change', async () => {
        const visible = input.checked;
        input.disabled = true;

        try {
            await http.patch(input.dataset.bannerDefaults, { visible: visible ? 1 : 0 });
            reload(card);
        } catch {
            input.checked = !visible;
        } finally {
            input.disabled = false;
        }
    });
}

function bindSorting(tbody, card, sortUrl) {
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
                    /* http.js already showed the error toast. */
                }

                /* The merged order (store + platform banners) comes from the server. */
                reload(card);
            },
        });
    });
}

export default function initVendorBanners(page) {
    bindRemoteModals(page);

    const table = page.querySelector('table#vendor-banners');
    const tbody = table?.tBodies[0];

    if (!table || !tbody) {
        return;
    }

    const card = table.closest('[data-datatable-card]');

    bindDefaultsSwitch(page, card);
    $(table).on('draw.dt', () => renumber(tbody));
    renumber(tbody);

    if (card?.dataset.bannerSort) {
        bindSorting(tbody, card, card.dataset.bannerSort);
    }
}
