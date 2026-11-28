/**
 * Sort pill of the Elora v1 listing (Figma 1:17311) as a Select2 (project rule). jQuery +
 * Select2 load only here; the native change event is re-dispatched so the shared
 * select[data-auto-filter] handler (catalog/listing.js) applies the sort.
 */
import { $ } from '../../../core/select2-lib.js';

export function initListingSort(select) {
    if (select.classList.contains('select2-hidden-accessible')) {
        return;
    }

    const $select = $(select);
    $select.select2({
        width: '100%',
        dir: document.documentElement.dir || 'ltr',
        minimumResultsForSearch: Infinity,
        dropdownParent: $(select.parentElement),
        dropdownAutoWidth: false,
    });
    $select.on('select2:select', () => select.dispatchEvent(new Event('change', { bubbles: true })));
    select.parentElement.querySelector('.select2-selection')?.setAttribute('aria-label', select.dataset.label ?? '');
}
