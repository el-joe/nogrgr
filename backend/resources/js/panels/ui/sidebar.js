/**
 * Panel sidebar (x-ui::sidebar, Figma Sidebar 37:12436) — sets <html data-sidebar-mode>
 * (the sidebar element itself is <aside data-sidebar-root>, so selectors never match <html>):
 *   expanded     ≥ 1280, default (264)
 *   collapsed    ≥ 1280 when the admin collapsed it (remembered), or 1024–1279 (76, icon-only)
 *   overlay      1024–1279 temporarily expanded over the content (scrim)
 *   drawer       < 1024, hidden off-canvas;  drawer-open = visible (288, scrim)
 * The collapsed choice and the open group live in localStorage (wrapped in try/catch:
 * private windows / blocked storage just fall back to the defaults).
 * While the drawer / overlay is open the content behind it is inert, so focus stays in
 * the sidebar (Esc, the scrim or the close button close it and focus returns to the toggle).
 * A sidebar with data-sidebar-pinned stays expanded at ≥ 1280 (no remembered collapse).
 * Groups: one open at a time. Keyboard: ↑ ↓ move between items, → / ← open / close a
 * group (mirrored in RTL). Collapsed mode: hovering or focusing a group shows a flyout.
 */
const COLLAPSED_KEY = 'nog.sidebar.collapsed';
const GROUP_KEY = 'nog.sidebar.group';

const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            if (value === null) {
                window.localStorage.removeItem(key);
            } else {
                window.localStorage.setItem(key, value);
            }
        } catch {
            // storage unavailable: keep the in-memory state only
        }
    },
};

const html = document.documentElement;
const xl = window.matchMedia('(min-width: 1280px)');
const lg = window.matchMedia('(min-width: 1024px)');

function baseMode() {
    if (xl.matches) {
        /* data-sidebar-pinned (vendor Tenant Panel side menu 12:15498): never collapses on wide screens. */
        if (sidebarElement()?.hasAttribute('data-sidebar-pinned')) {
            return 'expanded';
        }

        return storage.get(COLLAPSED_KEY) === '1' ? 'collapsed' : 'expanded';
    }

    return lg.matches ? 'collapsed' : 'drawer';
}

const sidebarElement = () => document.querySelector('aside[data-sidebar-root]');
const contentBehind = () => document.querySelector('.ui-shell-main');
let returnFocus = null;

function setMode(mode) {
    const previous = html.dataset.sidebarMode;
    html.dataset.sidebarMode = mode;
    const sidebar = sidebarElement();
    const modal = mode === 'drawer-open' || mode === 'overlay';
    const visible = ['expanded', 'overlay', 'drawer-open'].includes(mode);

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => toggle.setAttribute('aria-expanded', String(visible)));

    /* Only the off-canvas sidebar is inert when hidden; only the content behind is inert while it is open. */
    sidebar?.toggleAttribute('inert', mode === 'drawer');
    contentBehind()?.toggleAttribute('inert', modal);

    if (sidebar) {
        if (modal) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
    }

    if (modal && previous !== mode) {
        returnFocus = document.activeElement;
        requestAnimationFrame(() => (sidebar?.querySelector('.ui-nav-link[aria-current="page"]') ?? sidebar?.querySelector('a, button'))?.focus({ preventScroll: true }));
    } else if (!modal && (previous === 'drawer-open' || previous === 'overlay')) {
        returnFocus?.focus?.({ preventScroll: true });
        returnFocus = null;
    }

    if (mode !== 'collapsed') {
        closeFlyouts();
    }
}

export function toggleSidebar() {
    const mode = html.dataset.sidebarMode;

    if (xl.matches && sidebarElement()?.hasAttribute('data-sidebar-pinned')) {
        setMode('expanded');
    } else if (xl.matches) {
        const collapse = mode !== 'collapsed';
        storage.set(COLLAPSED_KEY, collapse ? '1' : null);
        setMode(collapse ? 'collapsed' : 'expanded');
    } else if (lg.matches) {
        setMode(mode === 'overlay' ? 'collapsed' : 'overlay');
    } else {
        setMode(mode === 'drawer-open' ? 'drawer' : 'drawer-open');
    }
}

function closeTransient() {
    if (html.dataset.sidebarMode === 'overlay') {
        setMode('collapsed');
    } else if (html.dataset.sidebarMode === 'drawer-open') {
        setMode('drawer');
    }
}

/* ---------- groups ---------- */

function setGroup(group, open, remember = true) {
    const button = group.querySelector(':scope > [data-nav-group-toggle]');

    if (open) {
        group.parentElement.querySelectorAll(':scope > [data-nav-group][data-open]').forEach((other) => other !== group && setGroup(other, false, false));
        group.dataset.open = '';
    } else {
        delete group.dataset.open;
    }

    button?.setAttribute('aria-expanded', String(open));

    if (remember) {
        storage.set(GROUP_KEY, open ? group.dataset.navGroup : null);
    }
}

/* ---------- flyouts (collapsed mode) ---------- */

function closeFlyouts() {
    document.querySelectorAll('.ui-nav-children[data-flyout]').forEach((list) => {
        delete list.dataset.flyout;
        list.style.removeProperty('top');
        list.style.removeProperty('inset-inline-start');
    });
}

function openFlyout(group) {
    if (html.dataset.sidebarMode !== 'collapsed') {
        return;
    }

    closeFlyouts();
    const list = group.querySelector(':scope > .ui-nav-children');
    const anchor = group.querySelector(':scope > .ui-nav-link');

    if (!list || !anchor) {
        return;
    }

    const rect = anchor.getBoundingClientRect();
    const sidebar = sidebarElement().getBoundingClientRect();
    list.dataset.flyout = '';
    list.style.top = `${Math.min(rect.top, window.innerHeight - list.offsetHeight - 8)}px`;
    list.style.setProperty('inset-inline-start', `${sidebar.width + 8}px`);
}

/* ---------- keyboard ---------- */

function visibleLinks(sidebar) {
    return [...sidebar.querySelectorAll('.ui-nav-link')].filter((link) => link.offsetParent !== null);
}

function onKeydown(event, sidebar) {
    const link = event.target.closest('.ui-nav-link');

    if (!link) {
        return;
    }

    const rtl = html.dir === 'rtl';
    const group = link.closest('[data-nav-group]');
    const isToggle = link.matches('[data-nav-group-toggle]');

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const links = visibleLinks(sidebar);
        const index = links.indexOf(link);
        links[(index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length]?.focus();
    } else if (event.key === (rtl ? 'ArrowLeft' : 'ArrowRight') && isToggle) {
        event.preventDefault();
        setGroup(group, true);
        group.querySelector('.ui-nav-children .ui-nav-link')?.focus();
    } else if (event.key === (rtl ? 'ArrowRight' : 'ArrowLeft') && group) {
        event.preventDefault();
        setGroup(group, false);
        group.querySelector(':scope > [data-nav-group-toggle]')?.focus();
    } else if (event.key === 'Escape') {
        closeFlyouts();
        closeTransient();
    }
}

let bound = false;

export function initSidebar() {
    const sidebar = sidebarElement();

    if (!sidebar || bound) {
        return;
    }

    bound = true;
    html.dataset.sidebarModeReady = 'false';
    setMode(baseMode());
    requestAnimationFrame(() => requestAnimationFrame(() => (html.dataset.sidebarModeReady = 'true')));

    /* Restore the remembered group unless the active item already opened one. */
    const remembered = storage.get(GROUP_KEY);

    if (!sidebar.querySelector('[data-nav-group][data-open]') && remembered) {
        const group = sidebar.querySelector(`[data-nav-group="${CSS.escape(remembered)}"]`);

        if (group) {
            setGroup(group, true, false);
        }
    }

    sidebar.querySelector('.ui-nav-link[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });

    const onChange = () => setMode(baseMode());
    xl.addEventListener('change', onChange);
    lg.addEventListener('change', onChange);

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sidebar-toggle]')) {
            event.preventDefault();
            toggleSidebar();
            return;
        }

        if (event.target.closest('[data-sidebar-scrim]')) {
            closeTransient();
            return;
        }

        const toggle = event.target.closest('[data-nav-group-toggle]');

        if (toggle && sidebar.contains(toggle)) {
            event.preventDefault();
            const group = toggle.closest('[data-nav-group]');

            if (html.dataset.sidebarMode === 'collapsed') {
                openFlyout(group);
                return;
            }

            setGroup(group, group.dataset.open === undefined);
            return;
        }

        if (!event.target.closest('.ui-nav-children[data-flyout]')) {
            closeFlyouts();
        }
    });

    sidebar.addEventListener('mouseover', (event) => {
        const group = event.target.closest('[data-nav-group]');

        if (group && html.dataset.sidebarMode === 'collapsed' && !event.target.closest('.ui-nav-children')) {
            openFlyout(group);
        }
    });

    sidebar.addEventListener('mouseleave', (event) => {
        if (!event.relatedTarget?.closest?.('.ui-nav-children[data-flyout]')) {
            closeFlyouts();
        }
    });

    document.addEventListener('mouseout', (event) => {
        const flyout = event.target.closest?.('.ui-nav-children[data-flyout]');

        if (flyout && !flyout.contains(event.relatedTarget) && !sidebar.contains(event.relatedTarget)) {
            closeFlyouts();
        }
    });

    sidebar.addEventListener('keydown', (event) => onKeydown(event, sidebar));

    document.addEventListener('keydown', (event) => {
        const modal = html.dataset.sidebarMode === 'drawer-open' || html.dataset.sidebarMode === 'overlay';

        if (event.key === 'Escape') {
            closeTransient();
        } else if (event.key === 'Tab' && modal) {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter((el) => el.offsetParent !== null);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (!sidebar.contains(document.activeElement)) {
                event.preventDefault();
                first?.focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });
}
