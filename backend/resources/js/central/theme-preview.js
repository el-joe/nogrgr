/**
 * Central theme live preview (WEB-004, [data-page="theme-preview"]): device switch
 * (desktop / tablet / mobile, remembered per browser), iframe loading state, theme menu
 * (click, Escape, outside click, arrow keys) and swipe between themes — touch swipe and
 * mouse drag on the theme title, plus the ← / → keys. Reduced-motion aware via CSS; the
 * page works without this module (plain links).
 */
const DEVICE_KEY = 'nogrgr.theme-preview.device';
const SWIPE_THRESHOLD = 60;

function storage(action, value) {
    try {
        return action === 'get' ? window.localStorage.getItem(DEVICE_KEY) : window.localStorage.setItem(DEVICE_KEY, value);
    } catch {
        return null;
    }
}

function initDevices(root) {
    const frame = root.querySelector('[data-tp-frame]');
    const buttons = [...root.querySelectorAll('[data-tp-device]')];

    if (!frame || buttons.length === 0) {
        return;
    }

    const apply = (device, animate = true) => {
        frame.dataset.device = device;
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.tpDevice === device)));

        if (animate) {
            frame.classList.remove('is-switching');
            void frame.offsetWidth;
            frame.classList.add('is-switching');
        }
    };

    const saved = storage('get');

    if (saved && buttons.some((button) => button.dataset.tpDevice === saved) && window.matchMedia('(min-width: 640px)').matches) {
        apply(saved, false);
    }

    buttons.forEach((button) => button.addEventListener('click', () => {
        apply(button.dataset.tpDevice);
        storage('set', button.dataset.tpDevice);
    }));
}

function initLoading(root) {
    const iframe = root.querySelector('[data-tp-iframe]');
    const loading = root.querySelector('[data-tp-loading]');

    if (!iframe || !loading) {
        return;
    }

    const done = () => loading.classList.add('is-done');
    iframe.addEventListener('load', done, { once: true });
    window.setTimeout(done, 12000);
}

function initMenu(root) {
    const menu = root.querySelector('[data-tp-menu]');
    const trigger = menu?.querySelector('[data-tp-menu-trigger]');
    const list = menu?.querySelector('[data-tp-menu-list]');

    if (!trigger || !list) {
        return;
    }

    const links = () => [...list.querySelectorAll('a')];
    const toggle = (open) => {
        trigger.setAttribute('aria-expanded', String(open));
        list.classList.toggle('is-open', open);

        if (open) {
            (list.querySelector('[aria-selected="true"] a') ?? links()[0])?.focus();
        }
    };

    trigger.addEventListener('click', () => toggle(trigger.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            toggle(false);
        }
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            toggle(false);
            trigger.focus();
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key) && list.classList.contains('is-open')) {
            event.preventDefault();
            const items = links();
            const current = items.indexOf(document.activeElement);
            const step = event.key === 'ArrowDown' ? 1 : -1;
            items[(current + step + items.length) % items.length]?.focus();
        }
    });
}

function initSwipe(root) {
    const zone = root.querySelector('[data-tp-swipe]');
    const prev = root.querySelector('[data-tp-prev]');
    const next = root.querySelector('[data-tp-next]');

    if (!zone || (!prev && !next)) {
        return;
    }

    const rtl = document.documentElement.dir === 'rtl';
    const go = (forward) => {
        // Swiping / pressing toward the reading end moves to the next theme (mirrored in RTL).
        const link = forward !== rtl ? next : prev;

        if (link) {
            window.location.assign(link.href);
        }
    };

    let start = null;

    zone.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.target.closest('[data-tp-menu-list]')) {
            return;
        }

        start = { x: event.clientX, y: event.clientY };
    });
    zone.addEventListener('pointermove', (event) => {
        if (start && Math.abs(event.clientX - start.x) > 8) {
            zone.classList.add('is-dragging');
        }
    });
    const end = (event) => {
        if (!start) {
            return;
        }

        const dx = event.clientX - start.x;
        const dy = event.clientY - start.y;
        start = null;
        zone.classList.remove('is-dragging');

        if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
            event.preventDefault();
            go(dx < 0);
        }
    };
    zone.addEventListener('pointerup', end);
    zone.addEventListener('pointercancel', () => {
        start = null;
        zone.classList.remove('is-dragging');
    });

    document.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, select, [data-tp-menu]') || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            go(event.key === 'ArrowRight');
        }
    });
}

export default function initThemePreview(root = document) {
    const page = root.querySelector('[data-theme-preview]');

    if (!page) {
        return;
    }

    initDevices(page);
    initLoading(page);
    initMenu(page);
    initSwipe(page);
}
