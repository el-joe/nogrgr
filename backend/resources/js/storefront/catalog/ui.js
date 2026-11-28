/**
 * Small Ecommet UI behaviours: simple tab lists ([data-simple-tabs]: deals, categories
 * browser), header categories mega-dropdown (hover / click / keys / outside / Esc),
 * popovers ([data-popover]), back-to-top button, "see all details" clamp, and CSS custom
 * properties fed from data-* (flash progress, delivery bars, review shares, swatches) so
 * views need no inline styles.
 */
import { prefersReducedMotion } from '../core/motion/utils';

function bindTabs(container) {
    const tabs = [...container.querySelectorAll(':scope [role="tablist"] [role="tab"]')].filter((tab) => tab.closest('[data-simple-tabs]') === container);

    const select = (tab, focus = false) => {
        tabs.forEach((other) => {
            const active = other === tab;
            other.setAttribute('aria-selected', String(active));
            other.tabIndex = active ? 0 : -1;
            const panel = document.getElementById(other.getAttribute('aria-controls'));

            if (panel) {
                panel.hidden = !active;
            }
        });

        if (focus) {
            tab.focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', (event) => {
            const vertical = tab.closest('[role="tablist"]')?.getAttribute('aria-orientation') === 'vertical';
            const rtl = document.documentElement.dir === 'rtl';
            const next = vertical ? 'ArrowDown' : rtl ? 'ArrowLeft' : 'ArrowRight';
            const prev = vertical ? 'ArrowUp' : rtl ? 'ArrowRight' : 'ArrowLeft';
            const step = event.key === next ? 1 : event.key === prev ? -1 : 0;

            if (step) {
                event.preventDefault();
                select(tabs[(index + step + tabs.length) % tabs.length], true);
            }
        });
    });
}

function bindMega(root) {
    const toggle = root.querySelector('[data-mega-toggle]');
    const panel = root.querySelector('[data-mega-panel]');
    let timer = null;

    let hovered = false;
    const set = (open) => {
        clearTimeout(timer);
        panel.toggleAttribute('data-open', open);
        toggle.setAttribute('aria-expanded', String(open));

        if (!open) {
            hovered = false;
        }
    };

    /* With a mouse the menu is already open from hover when the click lands: keep it open. */
    toggle.addEventListener('click', () => {
        if (hovered) {
            hovered = false;
            set(true);
            return;
        }

        set(toggle.getAttribute('aria-expanded') !== 'true');
    });
    root.addEventListener('mouseenter', () => {
        /* Back inside (e.g. after crossing the gap down to the panel): drop the pending close. */
        clearTimeout(timer);

        if (window.matchMedia('(hover: hover)').matches && toggle.getAttribute('aria-expanded') !== 'true') {
            set(true);
            hovered = true;
        }
    });
    root.addEventListener('mouseleave', () => {
        clearTimeout(timer);
        timer = setTimeout(() => set(false), 300);
    });
    root.addEventListener('keydown', (event) => event.key === 'Escape' && (set(false), toggle.focus()));
    document.addEventListener('click', (event) => !root.contains(event.target) && set(false));
    root.addEventListener('focusout', (event) => !root.contains(event.relatedTarget) && set(false));

    const tabs = [...panel.querySelectorAll('[data-mega-tab]')];
    const show = (tab) => tabs.forEach((other) => {
        other.setAttribute('aria-selected', String(other === tab));
        document.getElementById(other.getAttribute('aria-controls')).hidden = other !== tab;
    });
    tabs.forEach((tab, index) => {
        tab.addEventListener('mouseenter', () => show(tab));
        tab.addEventListener('focus', () => show(tab));
        tab.addEventListener('click', () => show(tab));
        tab.addEventListener('keydown', (event) => {
            const step = event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0;

            if (step) {
                event.preventDefault();
                tabs[(index + step + tabs.length) % tabs.length].focus();
            }
        });
    });
}

function bindPopover(root) {
    const toggle = root.querySelector('[data-popover-toggle]');
    const panel = root.querySelector('[data-popover-panel]');

    if (!toggle || !panel) {
        return;
    }

    const set = (open) => {
        panel.toggleAttribute('data-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        set(toggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('click', (event) => !panel.contains(event.target) && set(false));
    document.addEventListener('keydown', (event) => event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true' && (set(false), toggle.focus()));
}

function bindBackToTop(button) {
    const update = () => button.toggleAttribute('data-visible', window.scrollY > window.innerHeight);
    window.addEventListener('scroll', update, { passive: true });
    update();
    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        document.getElementById('main')?.focus({ preventScroll: true });
    });
}

/** Phone product title (m 1:3073): the chevron expands the two-line clamp; only offered when text is cut. */
function bindTitle(toggle) {
    const title = document.getElementById(toggle.getAttribute('aria-controls'));

    if (!title || title.scrollHeight <= title.clientHeight + 1) {
        return;
    }

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const open = title.classList.toggle('max-lg:line-clamp-2') === false;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.querySelector('svg')?.classList.toggle('rotate-180', open);
    });
}

function bindClamp(toggle) {
    const body = document.getElementById(toggle.getAttribute('aria-controls'));

    if (!body) {
        return;
    }

    if (body.scrollHeight <= body.clientHeight + 4) {
        body.removeAttribute('data-collapsed');
        toggle.hidden = true;
        return;
    }

    const label = toggle.querySelector('[data-clamp-label]');
    const more = label?.textContent;
    toggle.addEventListener('click', () => {
        const open = body.toggleAttribute('data-collapsed') === false;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.querySelector('svg')?.classList.toggle('rotate-180', open);

        if (label) {
            label.textContent = open ? toggle.dataset.less : more;
        }
    });
}

/** data-* → CSS custom properties (no inline styles in Blade). */
export function applyDataVars(root = document) {
    root.querySelectorAll('[data-progress]').forEach((track) => {
        const start = Date.parse(track.dataset.progressStart);
        const end = Date.parse(track.dataset.progressEnd);
        const live = Number.isFinite(start) && Number.isFinite(end) && end > start ? ((Date.now() - start) / (end - start)) * 100 : Number(track.dataset.progress);
        track.style.setProperty('--p', `${Math.max(0, Math.min(100, live || 0))}%`);
    });
    root.querySelectorAll('[data-bar]').forEach((bar) => {
        const max = Number(bar.dataset.barMax) || 1;
        bar.style.height = `${Math.max(4, (Number(bar.dataset.bar) / max) * 72)}px`;
    });
    root.querySelectorAll('[data-width]').forEach((bar) => bar.style.setProperty('--w', `${Number(bar.dataset.width) || 0}%`));
    root.querySelectorAll('[data-swatch]').forEach((swatch) => swatch.style.setProperty('--swatch', swatch.dataset.swatch || '#c4c4c4'));
}

export function initUi(root = document) {
    root.querySelectorAll('[data-simple-tabs]:not([data-ready])').forEach((container) => {
        container.dataset.ready = '';
        bindTabs(container);
    });
    root.querySelectorAll('[data-mega]:not([data-ready])').forEach((mega) => {
        mega.dataset.ready = '';
        bindMega(mega);
    });
    root.querySelectorAll('[data-popover]:not([data-ready])').forEach((popover) => {
        popover.dataset.ready = '';
        bindPopover(popover);
    });
    root.querySelectorAll('[data-back-to-top]:not([data-ready])').forEach((button) => {
        button.dataset.ready = '';
        bindBackToTop(button);
    });
    root.querySelectorAll('[data-title-toggle]:not([data-ready])').forEach((toggle) => {
        toggle.dataset.ready = '';
        bindTitle(toggle);
    });

    root.querySelectorAll('[data-clamp-toggle]:not([data-ready])').forEach((toggle) => {
        toggle.dataset.ready = '';
        bindClamp(toggle);
    });
    applyDataVars(root);
}
