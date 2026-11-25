/**
 * Filters bar (x-ui::filters-bar, Figma 33:10628), synced to the URL.
 *
 *   <div data-filters="products">            (target: the datatable id, or "" for page filters)
 *     <input name="search" data-filter data-filter-debounce>   (300ms debounce)
 *     <select name="status" data-filter data-filter-label="Status">…
 *     [data-filter-chips]  [data-filters-clear]  [data-filters-count]
 *   </div>
 * Fields outside the bar (e.g. in the "More filters" drawer) join with
 * data-filter-for="products". Values are written to the query string with
 * history.replaceState and restored from it on load. With a table target the table
 * reloads; without one (data-filters-reload) the page reloads with the new query.
 */
import { t } from '../../core/i18n';
import iconX from '../../../svg/icons/x.svg?raw';

const fieldsFor = (target) => {
    const bar = document.querySelector(`[data-filters="${CSS.escape(target)}"]`);
    const inside = bar ? [...bar.querySelectorAll('[data-filter]')] : [];
    const outside = [...document.querySelectorAll(`[data-filter-for="${CSS.escape(target)}"] [data-filter], [data-filter][data-filter-for="${CSS.escape(target)}"]`)];

    return [...new Set([...inside, ...outside])].filter((field) => field.name);
};

function valueOf(field) {
    if (field.type === 'checkbox') {
        return field.checked ? field.value : '';
    }

    if (field.multiple) {
        return [...field.selectedOptions].map((option) => option.value).filter(Boolean);
    }

    return field.value ?? '';
}

export function collectFilters(target) {
    const values = {};

    fieldsFor(target).forEach((field) => {
        const value = valueOf(field);

        if ((Array.isArray(value) && value.length) || (!Array.isArray(value) && value !== '')) {
            values[field.name.replace(/\[\]$/, '')] = value;
        }
    });

    return values;
}

export function hasActiveFilters(target) {
    return Object.keys(collectFilters(target)).length > 0;
}

function writeUrl(target) {
    const url = new URL(window.location.href);

    fieldsFor(target).forEach((field) => url.searchParams.delete(field.name.replace(/\[\]$/, '')));
    Object.entries(collectFilters(target)).forEach(([name, value]) => url.searchParams.set(name, Array.isArray(value) ? value.join(',') : value));
    window.history.replaceState(window.history.state, '', url);

    return url;
}

function readUrl(target) {
    const params = new URL(window.location.href).searchParams;

    fieldsFor(target).forEach((field) => {
        const name = field.name.replace(/\[\]$/, '');

        if (!params.has(name)) {
            return;
        }

        const value = params.get(name);

        if (field.type === 'checkbox') {
            field.checked = field.value === value;
        } else if (field.multiple) {
            const values = value.split(',');
            [...field.options].forEach((option) => (option.selected = values.includes(option.value)));
        } else {
            field.value = value;
        }
    });
}

function labelFor(field) {
    if (field.tagName === 'SELECT') {
        return [...field.selectedOptions].map((option) => option.textContent.trim()).join(', ');
    }

    const picker = field.closest('[data-datepicker]');

    if (picker) {
        return picker.querySelector('[data-date-display]')?.textContent.trim() ?? field.value;
    }

    return field.type === 'checkbox' ? '' : field.value;
}

function renderChips(target) {
    const bar = document.querySelector(`[data-filters="${CSS.escape(target)}"]`);
    const chips = bar?.querySelector('[data-filter-chips]');
    const fields = fieldsFor(target).filter((field) => field.dataset.filterLabel && valueOf(field).length !== 0 && valueOf(field) !== '');
    const seen = new Set();
    let count = 0;

    if (chips) {
        chips.querySelectorAll('[data-filter-chip]').forEach((chip) => chip.remove());
    }

    fields.forEach((field) => {
        const group = field.closest('[data-datepicker]') ?? field;

        if (seen.has(group)) {
            return;
        }

        seen.add(group);
        count++;

        if (!chips) {
            return;
        }

        const chip = document.createElement('span');
        chip.dataset.filterChip = '';
        chip.className = 'inline-flex h-6 items-center gap-1 rounded-chip bg-primary-subtle ps-2 pe-1 text-xs font-medium text-ink-brand';
        const text = document.createElement('span');
        text.textContent = `${field.dataset.filterLabel}: ${labelFor(field)}`;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'grid size-4 place-items-center rounded-full hover:bg-primary/10';
        remove.setAttribute('aria-label', t('filters.remove', { name: field.dataset.filterLabel }));
        remove.innerHTML = iconX.replace('<svg', '<svg width="12" height="12" class="ui-icon" aria-hidden="true"');
        remove.addEventListener('click', () => clearFields(target, group === field ? [field] : [...group.querySelectorAll('[data-filter]')]));
        chip.append(text, remove);
        chips.insertBefore(chip, chips.querySelector('[data-filters-clear]'));
    });

    if (chips) {
        chips.hidden = count === 0 || bar.dataset.filtersChips === 'false';
    }

    document.querySelectorAll(`[data-filters-count="${CSS.escape(target)}"]`).forEach((badge) => {
        badge.textContent = String(count);
        badge.hidden = count === 0;
    });
}

let clearing = false;

function clearFields(target, fields) {
    clearing = true;
    fields.forEach((field) => {
        if (field.type === 'checkbox') {
            field.checked = false;
        } else if (field.multiple) {
            [...field.options].forEach((option) => (option.selected = false));
        } else {
            field.value = '';
        }
    });

    fields.forEach((field) => {
        const picker = field.closest('[data-datepicker]');

        if (picker?.__datepicker) {
            picker.__datepicker.from = null;
            picker.__datepicker.to = null;
            picker.__datepicker.renderDisplay();
        }

        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    clearing = false;
    apply(target);
}

const timers = new Map();

function apply(target) {
    const url = writeUrl(target);
    renderChips(target);
    const bar = document.querySelector(`[data-filters="${CSS.escape(target)}"]`);

    if (bar?.dataset.filtersReload !== undefined) {
        window.location.assign(url);
        return;
    }

    document.getElementById(target)?.closest('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload'));
}

function schedule(target, delay) {
    clearTimeout(timers.get(target));
    timers.set(target, setTimeout(() => apply(target), delay));
}

let bound = false;

/** Re-render chips once date pickers have formatted their display text. */
export function refreshFilterChips(root = document) {
    root.querySelectorAll('[data-filters]').forEach((bar) => renderChips(bar.dataset.filters));
}

export function initFilters(root = document) {
    root.querySelectorAll('[data-filters]:not([data-filters-ready])').forEach((bar) => {
        bar.dataset.filtersReady = '';
        readUrl(bar.dataset.filters);
    });

    if (bound) {
        return;
    }

    bound = true;

    const targetOf = (field) => field.closest('[data-filters]')?.dataset.filters ?? field.closest('[data-filter-for]')?.dataset.filterFor ?? field.dataset.filterFor;

    document.addEventListener('input', (event) => {
        if (event.target.matches?.('[data-filter][data-filter-debounce]')) {
            schedule(targetOf(event.target), 300);
        }
    });

    document.addEventListener('change', (event) => {
        const field = event.target;

        if (!field.matches?.('[data-filter]') || field.matches('[data-filter-debounce]') || field.closest('[data-filter-defer]')) {
            return;
        }

        const target = targetOf(field);

        if (target !== undefined && !clearing) {
            schedule(target, 0);
        }
    });

    document.addEventListener('click', (event) => {
        const clear = event.target.closest?.('[data-filters-clear]');
        const applyButton = event.target.closest?.('[data-filters-apply]');

        if (clear) {
            const target = clear.dataset.filtersClear || clear.closest('[data-filters]')?.dataset.filters || '';
            clearFields(target, fieldsFor(target));
        } else if (applyButton) {
            apply(applyButton.dataset.filtersApply);
        }
    });
}
