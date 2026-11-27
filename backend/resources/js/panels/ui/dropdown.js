/**
 * Dropdowns / menus (x-ui::dropdown, row actions, user menu, notifications).
 *   <div data-dropdown>
 *     <button data-dropdown-trigger aria-expanded="false" aria-controls="m1">…</button>
 *     <div class="ui-popover" id="m1" data-dropdown-menu role="menu">…[role=menuitem]…</div>
 *   </div>
 * Outside click / Esc close; ↑ ↓ Home End move between items; one menu open at a time.
 */
let bound = false;

const menuOf = (dropdown) => dropdown.querySelector(':scope > [data-dropdown-menu]');
const triggerOf = (dropdown) => dropdown.querySelector(':scope > [data-dropdown-trigger]');
const itemsOf = (menu) => [...menu.querySelectorAll('[role="menuitem"]:not([disabled]), [data-menu-item]:not([disabled])')];

const FLOAT_PROPS = ['position', 'top', 'left', 'right', 'bottom', 'margin'];

/** Menus inside a datatable are clipped by its scroll wrapper, so float them (fixed) next to the trigger. */
function floatMenu(dropdown, menu) {
    const trigger = triggerOf(dropdown);

    if (!trigger || !dropdown.closest('[data-datatable-card]')) {
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;
    const rtl = getComputedStyle(dropdown).direction === 'rtl';
    let left = rtl ? rect.left : rect.right - width;
    left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
    let top = rect.bottom + 4;

    if (top + height > window.innerHeight - 8 && rect.top - height - 4 > 8) {
        top = rect.top - height - 4;
    }

    Object.assign(menu.style, { position: 'fixed', top: `${top}px`, left: `${left}px`, right: 'auto', bottom: 'auto', margin: '0' });
}

function unfloatMenu(menu) {
    FLOAT_PROPS.forEach((prop) => menu.style.removeProperty(prop));
}

const closeFloating = (event) => {
    if (event.target?.closest?.('[data-dropdown-menu]')) {
        return;
    }

    document.querySelectorAll('[data-datatable-card] [data-dropdown]').forEach((dropdown) => closeDropdown(dropdown));
};

export function closeDropdown(dropdown, { focusTrigger = false } = {}) {
    const menu = menuOf(dropdown);

    if (!menu || menu.dataset.open === undefined) {
        return;
    }

    delete menu.dataset.open;
    unfloatMenu(menu);
    triggerOf(dropdown)?.setAttribute('aria-expanded', 'false');
    dropdown.dispatchEvent(new CustomEvent('dropdown:close', { bubbles: true }));

    if (focusTrigger) {
        triggerOf(dropdown)?.focus();
    }
}

export function openDropdown(dropdown, { focusFirst = false } = {}) {
    document.querySelectorAll('[data-dropdown]').forEach((other) => other !== dropdown && !other.contains(dropdown) && closeDropdown(other));
    const menu = menuOf(dropdown);

    if (!menu) {
        return;
    }

    menu.dataset.open = '';
    floatMenu(dropdown, menu);
    triggerOf(dropdown)?.setAttribute('aria-expanded', 'true');
    dropdown.dispatchEvent(new CustomEvent('dropdown:open', { bubbles: true }));

    if (focusFirst) {
        itemsOf(menu)[0]?.focus();
    }
}

export function initDropdowns() {
    if (bound) {
        return;
    }

    bound = true;
    window.addEventListener('scroll', closeFloating, true);
    window.addEventListener('resize', closeFloating);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-dropdown-trigger]');

        if (trigger) {
            event.preventDefault();
            const dropdown = trigger.closest('[data-dropdown]');
            menuOf(dropdown)?.dataset.open !== undefined ? closeDropdown(dropdown) : openDropdown(dropdown);
            return;
        }

        document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            const outside = !dropdown.contains(event.target);
            const pickedItem = menuOf(dropdown)?.contains(event.target) && event.target.closest('[role="menuitem"], [data-menu-item]') && event.target.closest('[data-dropdown]') === dropdown;

            if (outside || pickedItem) {
                closeDropdown(dropdown);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        const dropdown = event.target.closest?.('[data-dropdown]');

        if (!dropdown) {
            return;
        }

        const menu = menuOf(dropdown);
        const open = menu?.dataset.open !== undefined;

        if (event.key === 'Escape' && open) {
            event.preventDefault();
            closeDropdown(dropdown, { focusTrigger: true });
            return;
        }

        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            return;
        }

        if (!open && event.target.matches('[data-dropdown-trigger]')) {
            event.preventDefault();
            openDropdown(dropdown, { focusFirst: true });
            return;
        }

        const items = itemsOf(menu);
        const index = items.indexOf(document.activeElement);

        if (!items.length || index === -1) {
            return;
        }

        event.preventDefault();
        const next = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: items.length - 1 }[event.key];
        items[(next + items.length) % items.length].focus();
    });
}
