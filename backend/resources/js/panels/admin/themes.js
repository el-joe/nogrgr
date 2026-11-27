/**
 * Admin › Storefront › Themes (ADM-086), Home sections (ADM-087) and Template parts
 * (ADM-088): remote modals, the Countries modal helpers (search, select all / clear all),
 * the theme picker of the home sections page and drag & drop ordering of the sections
 * (SortableJS; the hidden order[] inputs follow the list, saved with the form).
 *
 *   [data-theme-countries]   countries form: [data-country-search] [data-country-all] [data-country-none]
 *   [data-theme-picker]      select whose option values are page URLs
 *   [data-theme-sections]    sections form: [data-section-list] > [data-section-item]
 */
import { $ } from '../vendor-libs';
import { loadSortable } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';

function countryItems(form) {
    return [...form.querySelectorAll('[data-country-item]')];
}

function bindCountries(page) {
    page.addEventListener('input', (event) => {
        const input = event.target.closest('[data-country-search]');
        const form = input?.closest('[data-theme-countries]');

        if (!form) {
            return;
        }

        const term = input.value.trim().toLowerCase();
        let visible = 0;

        countryItems(form).forEach((item) => {
            const match = term === '' || item.dataset.name.includes(term);
            item.hidden = !match;
            visible += match ? 1 : 0;
        });

        form.querySelector('[data-country-empty]')?.classList.toggle('hidden', visible > 0);
    });

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-country-all], [data-country-none]');
        const form = button?.closest('[data-theme-countries]');

        if (!form) {
            return;
        }

        const checked = button.hasAttribute('data-country-all');

        countryItems(form)
            .filter((item) => !item.hidden)
            .forEach((item) => {
                const box = item.querySelector('input[type="checkbox"]');

                if (box) {
                    box.checked = checked;
                }
            });
    });
}

function bindPicker(page) {
    const picker = page.querySelector('[data-theme-picker] select, select[data-theme-picker]');

    if (picker) {
        $(picker).on('change', () => {
            if (picker.value) {
                window.location.assign(picker.value);
            }
        });
    }
}

function bindSections(page) {
    const form = page.querySelector('[data-theme-sections]');
    const list = form?.querySelector('[data-section-list]');

    if (!form || !list) {
        return;
    }

    const counter = page.querySelector('[data-sections-count]');
    const refresh = () => {
        list.querySelectorAll('[data-section-item]').forEach((item, index) => {
            const position = item.querySelector('[data-sort-position]');

            if (position) {
                position.textContent = String(index + 1);
            }
        });

        if (counter?.dataset.template) {
            counter.textContent = counter.dataset.template.replace(':enabled', String(list.querySelectorAll('[data-section-toggle]:checked').length));
        }
    };

    list.addEventListener('change', refresh);

    if (form.hasAttribute('data-readonly')) {
        return;
    }

    loadSortable().then((Sortable) => {
        Sortable.create(list, {
            handle: '[data-drag-handle]',
            draggable: '[data-section-item]',
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd: refresh,
        });
    });
}

export default function initThemes(page) {
    bindRemoteModals(page);
    bindCountries(page);
    bindPicker(page);
    bindSections(page);
}
