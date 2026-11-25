/**
 * Searchable country select (Figma Storefront additions §2): flag + name, search box,
 * "No countries found", clear ×, opens above the field when there is no room below,
 * full keyboard support (↑ ↓ Home End Enter Esc, type to search).
 *
 * Progressive enhancement over the native <select data-country-select> inside
 * [data-sf-combobox]: the select keeps the value (country_id), stays the element the
 * 422 renderer marks aria-invalid, and receives a `change` event on every pick, so
 * the city suggestions and anything else listening to it keep working.
 */
import { placement } from './placement';

let sequence = 0;

const flag = (iso2) => {
    const span = document.createElement('span');
    span.className = iso2 ? `iti__flag iti__${iso2.toLowerCase()} sf-flag-sprite` : 'sf-flag';
    span.setAttribute('aria-hidden', 'true');

    return span;
};

export function initCombobox(root) {
    if (root.__combobox) {
        return root.__combobox;
    }

    const select = root.querySelector('select');

    if (!select) {
        return null;
    }

    const id = `sf-combobox-${++sequence}`;
    const options = [...select.options].filter((option) => option.value !== '');
    const placeholder = select.querySelector('option[value=""]')?.textContent ?? '';

    select.hidden = true;
    select.tabIndex = -1;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sf-control sf-combobox-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `${id}-list`);
    if (select.id) {
        // The field <label for> points at the select; move the id to the visible control.
        trigger.id = select.id;
        select.id = `${select.id}-native`;
    }
    for (const attribute of ['aria-describedby', 'aria-invalid']) {
        if (select.hasAttribute(attribute)) {
            trigger.setAttribute(attribute, select.getAttribute(attribute));
        }
    }
    trigger.innerHTML = '<span class="sf-combobox-flag"></span><span class="sf-combobox-value min-w-0 flex-1 truncate text-start"></span>'
        + `<span class="sf-combobox-clear" role="button" tabindex="-1" aria-label="${root.dataset.clearText ?? ''}">×</span>`
        + '<svg class="sf-combobox-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    const popover = document.createElement('div');
    popover.className = 'sf-popover';
    popover.innerHTML = `<div class="sf-combobox-search"><input type="search" autocomplete="off" aria-label="${root.dataset.searchText ?? ''}" placeholder="${root.dataset.searchText ?? ''}" aria-controls="${id}-list"></div>`
        + `<ul id="${id}-list" role="listbox"></ul><p class="sf-combobox-empty" hidden>${root.dataset.emptyText ?? ''}</p>`;

    root.append(trigger, popover);
    const search = popover.querySelector('input');
    const list = popover.querySelector('ul');
    const empty = popover.querySelector('.sf-combobox-empty');
    const value = trigger.querySelector('.sf-combobox-value');
    const flagSlot = trigger.querySelector('.sf-combobox-flag');
    const clear = trigger.querySelector('.sf-combobox-clear');

    const items = options.map((option) => {
        const item = document.createElement('li');
        item.id = `${id}-${option.value}`;
        item.className = 'sf-option cursor-pointer';
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.append(flag(option.dataset.iso2), Object.assign(document.createElement('span'), { textContent: option.textContent.trim(), className: 'truncate' }));
        list.append(item);

        return { item, option, text: `${option.textContent} ${option.dataset.iso2 ?? ''}`.toLowerCase() };
    });

    let active = -1;
    const visible = () => [...list.children].filter((item) => !item.hidden).map((item) => items.find((entry) => entry.item === item));

    const render = () => {
        const selected = select.selectedOptions[0];
        const chosen = selected && selected.value !== '';
        value.textContent = chosen ? selected.textContent.trim() : placeholder;
        value.classList.toggle('text-sf-muted', !chosen);
        flagSlot.replaceChildren(...(chosen ? [flag(selected.dataset.iso2)] : []));
        clear.hidden = !chosen;
        items.forEach(({ item, option }) => item.setAttribute('aria-selected', String(option.selected && chosen)));
        trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') ?? 'false');
    };

    const highlight = (index) => {
        const shown = visible();
        items.forEach(({ item }) => delete item.dataset.active);
        active = Math.max(0, Math.min(index, shown.length - 1));
        const current = shown[active]?.item;

        if (current) {
            current.dataset.active = '';
            current.scrollIntoView({ block: 'nearest' });
            search.setAttribute('aria-activedescendant', current.id);
        } else {
            search.removeAttribute('aria-activedescendant');
        }
    };

    const filter = () => {
        const query = search.value.trim().toLowerCase();
        items.forEach((entry) => {
            entry.item.hidden = query !== '' && !entry.text.includes(query);
        });
        // Names that start with the query first ("sau" → Saudi Arabia before Guinea-Bissau).
        const starts = ({ option }) => (query !== '' && option.textContent.trim().toLowerCase().startsWith(query) ? 0 : 1);
        list.append(...[...items].sort((a, b) => starts(a) - starts(b) || items.indexOf(a) - items.indexOf(b)).map(({ item }) => item));
        empty.hidden = visible().length > 0;
        highlight(0);
    };

    const isOpen = () => popover.dataset.open !== undefined;

    const open = () => {
        if (isOpen()) {
            return;
        }

        popover.dataset.placement = placement(trigger, 300);
        popover.dataset.open = '';
        trigger.setAttribute('aria-expanded', 'true');
        search.value = '';
        filter();
        const selectedIndex = visible().findIndex(({ option }) => option.selected);
        highlight(selectedIndex < 0 ? 0 : selectedIndex);
        search.focus({ preventScroll: true });
    };

    const close = (focus = true) => {
        if (!isOpen()) {
            return;
        }

        delete popover.dataset.open;
        trigger.setAttribute('aria-expanded', 'false');
        if (focus) {
            trigger.focus({ preventScroll: true });
        }
    };

    const pick = (optionValue) => {
        select.value = optionValue;
        select.removeAttribute('aria-invalid');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        render();
        close();
    };

    trigger.addEventListener('click', (event) => {
        if (event.target.closest('.sf-combobox-clear')) {
            event.stopPropagation();
            pick('');

            return;
        }

        isOpen() ? close() : open();
    });

    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        } else if (event.key.length === 1 && /\S/.test(event.key)) {
            open();
            search.value = event.key;
            filter();
            event.preventDefault();
        } else if ((event.key === 'Backspace' || event.key === 'Delete') && select.value) {
            pick('');
        }
    });

    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        const shown = visible();

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                highlight(active + 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                highlight(active - 1);
                break;
            case 'Home':
                event.preventDefault();
                highlight(0);
                break;
            case 'End':
                event.preventDefault();
                highlight(shown.length - 1);
                break;
            case 'Enter':
                event.preventDefault();
                if (shown[active]) {
                    pick(shown[active].option.value);
                }
                break;
            case 'Escape':
                event.preventDefault();
                event.stopPropagation(); // keep the surrounding sheet open
                close();
                break;
            case 'Tab':
                close(false);
                break;
            default:
        }
    });

    list.addEventListener('pointerdown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const item = event.target.closest('[role="option"]');

        if (item) {
            pick(item.dataset.value);
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (isOpen() && !root.contains(event.target)) {
            close(false);
        }
    });

    // The 422 renderer flags the select; mirror it on the visible control.
    new MutationObserver(render).observe(select, { attributes: true, attributeFilter: ['aria-invalid'] });
    select.addEventListener('change', render);
    select.form?.addEventListener('reset', () => setTimeout(render));

    render();
    root.__combobox = { render, open, close, set: (next) => { select.value = next ?? ''; render(); } };

    return root.__combobox;
}

export function initComboboxes(root = document) {
    root.querySelectorAll('[data-sf-combobox]').forEach(initCombobox);
}
