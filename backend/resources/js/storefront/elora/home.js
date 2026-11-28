/**
 * Elora shell + home behaviour:
 *  - category mega menu (navbar 1:20989): [data-elo-menu-toggle] opens [data-elo-mega];
 *    hovering / focusing a category tab shows its sub-categories; Esc, outside click and
 *    scrolling the page away close it.
 *  - v1 product tabs ([data-elo-tabs]): pill tabs switch the rail panels (arrows / Home /
 *    End move between tabs, mirrored in RTL); a shown rail re-measures its Swiper.
 */
import { isRtl } from '../core/motion/utils';
import { toast } from '../../core/notify';
import { t } from '../../core/i18n';

function initMegaMenu(root) {
    const bar = root.querySelector('[data-elo-menu]');
    const toggle = bar?.querySelector('[data-elo-menu-toggle]');
    const mega = bar?.querySelector('[data-elo-mega]');

    if (!toggle || !mega) {
        return;
    }

    const label = toggle.querySelector('[data-elo-menu-label]');
    const set = (open) => {
        mega.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? toggle.dataset.labelClose : toggle.dataset.labelOpen);
        if (label) {
            label.setAttribute('aria-hidden', 'true');
            label.textContent = (open ? toggle.dataset.textClose : toggle.dataset.textMenu) ?? label.textContent;
        }
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

    const tabs = [...mega.querySelectorAll('[data-elo-mega-tab]')];
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

function initTabs(root) {
    root.querySelectorAll('[data-elo-tabs]').forEach((section) => {
        const tabs = [...section.querySelectorAll('[role="tab"]')];

        const select = (index, focus = false) => {
            tabs.forEach((tab, position) => {
                const active = position === index;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                const panel = document.getElementById(tab.getAttribute('aria-controls'));

                if (panel) {
                    panel.hidden = !active;

                    if (active) {
                        panel.querySelectorAll('.swiper').forEach((element) => element.swiper?.update());
                    }
                }
            });

            if (focus) {
                tabs[index]?.focus();
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => select(index));
            tab.addEventListener('keydown', (event) => {
                const step = isRtl(section) ? -1 : 1;
                const moves = { ArrowRight: index + step, ArrowLeft: index - step, Home: 0, End: tabs.length - 1 };

                if (event.key in moves) {
                    event.preventDefault();
                    select((moves[event.key] + tabs.length) % tabs.length, true);
                }
            });
        });
    });
}

/* v1 flash sale "Use Code: …" chip: copies the code (execCommand where the Clipboard API is not offered, e.g. plain http). */
async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.className = 'sr-only';
    document.body.append(field);
    field.select();
    const copied = document.execCommand('copy');
    field.remove();

    if (!copied) {
        throw new Error('copy failed');
    }
}

function initCopyCode(root) {
    root.querySelectorAll('[data-copy-code]').forEach((button) => {
        button.addEventListener('click', () => {
            copyText(button.dataset.copyCode)
                .then(() => toast('success', t('ui.copied')))
                .catch(() => toast('info', t('ui.copy_failed')));
        });
    });
}

export function initEloraHome(root = document) {
    initCopyCode(root);
    initMegaMenu(root);
    initTabs(root);
}
