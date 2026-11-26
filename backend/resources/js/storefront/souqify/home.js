/**
 * Souqify shell + home behaviour:
 *  - "Shop By Categories" mega menu (navbar 1:81340): [data-sq-menu-toggle] opens
 *    [data-sq-mega]; hovering / focusing a category tab shows its sub-categories; Esc and an
 *    outside click close it.
 * Rails, hero, spotlight sliders, marquee, countdowns and reveals come from core/motion via
 * data-* attributes (no per-theme copies).
 */
function initMegaMenu(root) {
    const bar = root.querySelector('[data-sq-menu]');
    const toggle = bar?.querySelector('[data-sq-menu-toggle]');
    const mega = bar?.querySelector('[data-sq-mega]');

    if (!toggle || !mega) {
        return;
    }

    const set = (open) => {
        mega.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => set(mega.hidden));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !mega.hidden) {
            set(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!mega.hidden && !bar.contains(event.target)) {
            set(false);
        }
    });

    const tabs = [...mega.querySelectorAll('[data-sq-mega-tab]')];
    const show = (tab) => {
        tabs.forEach((other) => {
            const active = other === tab;
            other.setAttribute('aria-selected', String(active));
            const panel = document.getElementById(other.getAttribute('aria-controls'));

            if (panel) {
                panel.hidden = !active;
            }
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('mouseenter', () => show(tab));
        tab.addEventListener('focus', () => show(tab));
    });
}

export function initSouqifyHome(root = document) {
    initMegaMenu(root);
}
